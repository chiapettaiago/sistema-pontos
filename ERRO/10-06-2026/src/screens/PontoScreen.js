// src/screens/PontoScreen.js
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
  Vibration,
} from 'react-native';
import Geolocation from 'react-native-geolocation-service';
import { request, PERMISSIONS, RESULTS } from 'react-native-permissions';
import { Platform } from 'react-native';
import { pontoService } from '../services/api';
import { savePontoOffline, getPontosOffline } from '../services/storage';
import NetInfo from '@react-native-community/netinfo';

export default function PontoScreen({ navigation }) {
  const [pontosHoje, setPontosHoje] = useState({
    entrada: null,
    saida_almoco: null,
    volta_almoco: null,
    saida: null,
  });
  const [loading, setLoading] = useState(false);
  const [location, setLocation] = useState(null);
  const [isOnline, setIsOnline] = useState(true);

  useEffect(() => {
    carregarPontosHoje();
    requestLocationPermission();
    
    const unsubscribe = NetInfo.addEventListener(state => {
      setIsOnline(state.isConnected);
    });
    
    return () => unsubscribe();
  }, []);

  const requestLocationPermission = async () => {
    try {
      const permission = Platform.OS === 'ios' 
        ? PERMISSIONS.IOS.LOCATION_WHEN_IN_USE 
        : PERMISSIONS.ANDROID.ACCESS_FINE_LOCATION;
      
      const result = await request(permission);
      
      if (result === RESULTS.GRANTED) {
        getCurrentLocation();
      }
    } catch (error) {
      console.error('Erro ao solicitar permissão de localização:', error);
    }
  };

  const getCurrentLocation = () => {
    Geolocation.getCurrentPosition(
      (position) => {
        setLocation({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
        });
      },
      (error) => {
        console.error('Erro ao obter localização:', error);
      },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 10000 }
    );
  };

  const carregarPontosHoje = async () => {
    try {
      const response = await pontoService.getHoje();
      if (response.success) {
        const pontos = {};
        response.pontos.forEach(p => {
          pontos[p.tipo] = p.horario;
        });
        setPontosHoje(pontos);
      }
    } catch (error) {
      console.error('Erro ao carregar pontos:', error);
    }
  };

  const getProximoTipo = () => {
    if (!pontosHoje.entrada) return 'entrada';
    if (!pontosHoje.saida_almoco) return 'saida_almoco';
    if (!pontosHoje.volta_almoco) return 'volta_almoco';
    if (!pontosHoje.saida) return 'saida';
    return null;
  };

  const getTipoNome = (tipo) => {
    const nomes = {
      entrada: 'Entrada',
      saida_almoco: 'Saída para Almoço',
      volta_almoco: 'Volta do Almoço',
      saida: 'Saída',
    };
    return nomes[tipo];
  };

  const getTipoIcon = (tipo) => {
    const icons = {
      entrada: '✅',
      saida_almoco: '🍽️',
      volta_almoco: '🔄',
      saida: '🏁',
    };
    return icons[tipo];
  };

  const registrarPonto = async () => {
    const tipo = getProximoTipo();
    
    if (!tipo) {
      Alert.alert('Aviso', 'Você já finalizou o dia de hoje!');
      return;
    }

    setLoading(true);
    Vibration.vibrate(100);

    try {
      const pontoData = {
        tipo,
        latitude: location?.latitude,
        longitude: location?.longitude,
      };

      let response;
      
      if (isOnline) {
        response = await pontoService.registrar(tipo, location?.latitude, location?.longitude);
      } else {
        // Modo offline - salvar localmente
        const idLocal = await savePontoOffline(pontoData);
        response = { success: true, offline: true, id_local: idLocal };
      }

      if (response.success) {
        const horarioAtual = new Date().toLocaleTimeString('pt-BR', {
          hour: '2-digit',
          minute: '2-digit',
        });
        
        setPontosHoje(prev => ({ ...prev, [tipo]: horarioAtual }));
        
        const mensagem = response.offline 
          ? 'Ponto registrado offline! Será sincronizado quando houver internet.'
          : `${getTipoNome(tipo)} registrada às ${horarioAtual}`;
        
        Alert.alert('Sucesso', mensagem);
        
        if (!response.offline && tipo === 'saida') {
          navigation.goBack();
        }
      } else {
        Alert.alert('Erro', response.error || 'Não foi possível registrar o ponto');
      }
    } catch (error) {
      Alert.alert('Erro', 'Não foi possível registrar o ponto');
    } finally {
      setLoading(false);
    }
  };

  const proximoTipo = getProximoTipo();
  const diaFinalizado = !proximoTipo;

  return (
    <View style={styles.container}>
      <View style={styles.card}>
        <Text style={styles.title}>Registro de Ponto</Text>
        <Text style={styles.date}>{new Date().toLocaleDateString('pt-BR')}</Text>
        
        <View style={styles.statusGrid}>
          <View style={[styles.statusItem, pontosHoje.entrada && styles.statusCompleted]}>
            <Text style={styles.statusIcon}>✅</Text>
            <Text style={styles.statusLabel}>Entrada</Text>
            <Text style={styles.statusTime}>{pontosHoje.entrada || '--:--'}</Text>
          </View>
          <View style={[styles.statusItem, pontosHoje.saida_almoco && styles.statusCompleted]}>
            <Text style={styles.statusIcon}>🍽️</Text>
            <Text style={styles.statusLabel}>Saída Almoço</Text>
            <Text style={styles.statusTime}>{pontosHoje.saida_almoco || '--:--'}</Text>
          </View>
          <View style={[styles.statusItem, pontosHoje.volta_almoco && styles.statusCompleted]}>
            <Text style={styles.statusIcon}>🔄</Text>
            <Text style={styles.statusLabel}>Volta Almoço</Text>
            <Text style={styles.statusTime}>{pontosHoje.volta_almoco || '--:--'}</Text>
          </View>
          <View style={[styles.statusItem, pontosHoje.saida && styles.statusCompleted]}>
            <Text style={styles.statusIcon}>🏁</Text>
            <Text style={styles.statusLabel}>Saída</Text>
            <Text style={styles.statusTime}>{pontosHoje.saida || '--:--'}</Text>
          </View>
        </View>

        {diaFinalizado ? (
          <View style={styles.dayCompleted}>
            <Text style={styles.dayCompletedIcon}>🎉</Text>
            <Text style={styles.dayCompletedText}>Dia finalizado!</Text>
            <Text style={styles.dayCompletedSubtext}>
              Você já registrou todos os pontos de hoje.
            </Text>
          </View>
        ) : (
          <TouchableOpacity
            style={[styles.pontoButton, loading && styles.buttonDisabled]}
            onPress={registrarPonto}
            disabled={loading}
          >
            {loading ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <>
                <Text style={styles.pontoButtonIcon}>
                  {getTipoIcon(proximoTipo)}
                </Text>
                <Text style={styles.pontoButtonText}>
                  Registrar {getTipoNome(proximoTipo)}
                </Text>
              </>
            )}
          </TouchableOpacity>
        )}

        <View style={styles.infoBox}>
          <Text style={styles.infoText}>
            {isOnline ? '🟢 Online' : '🔴 Offline - Registros serão sincronizados depois'}
          </Text>
          {location && (
            <Text style={styles.infoText}>
              📍 Localização capturada
            </Text>
          )}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
    padding: 16,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 24,
    padding: 24,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 8,
    elevation: 4,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
    textAlign: 'center',
  },
  date: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
    marginTop: 4,
    marginBottom: 24,
  },
  statusGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    marginBottom: 24,
  },
  statusItem: {
    flex: 1,
    minWidth: '45%',
    backgroundColor: '#f5f5f5',
    borderRadius: 16,
    padding: 16,
    alignItems: 'center',
  },
  statusCompleted: {
    backgroundColor: '#d1fae5',
  },
  statusIcon: {
    fontSize: 24,
    marginBottom: 8,
  },
  statusLabel: {
    fontSize: 12,
    color: '#666',
  },
  statusTime: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginTop: 4,
  },
  pontoButton: {
    flexDirection: 'row',
    backgroundColor: '#667eea',
    borderRadius: 16,
    padding: 18,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
    marginBottom: 24,
  },
  buttonDisabled: {
    opacity: 0.7,
  },
  pontoButtonIcon: {
    fontSize: 24,
  },
  pontoButtonText: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#fff',
  },
  dayCompleted: {
    alignItems: 'center',
    padding: 24,
    backgroundColor: '#d1fae5',
    borderRadius: 16,
    marginBottom: 24,
  },
  dayCompletedIcon: {
    fontSize: 48,
    marginBottom: 12,
  },
  dayCompletedText: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#059669',
  },
  dayCompletedSubtext: {
    fontSize: 12,
    color: '#059669',
    marginTop: 4,
  },
  infoBox: {
    backgroundColor: '#f5f5f5',
    borderRadius: 12,
    padding: 12,
    alignItems: 'center',
  },
  infoText: {
    fontSize: 12,
    color: '#666',
  },
});