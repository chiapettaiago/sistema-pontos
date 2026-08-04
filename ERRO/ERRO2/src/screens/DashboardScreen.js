// src/screens/DashboardScreen.js
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  RefreshControl,
  Alert,
} from 'react-native';
import { authService, pontoService } from '../services/api';
import { syncNow } from '../services/sync';
import NetInfo from '@react-native-community/netinfo';

export default function DashboardScreen({ navigation }) {
  const [usuario, setUsuario] = useState(null);
  const [pontosHoje, setPontosHoje] = useState([]);
  const [stats, setStats] = useState({
    totalPontos: 0,
    diasTrabalhados: 0,
    horasExtras: 0,
  });
  const [refreshing, setRefreshing] = useState(false);
  const [isOnline, setIsOnline] = useState(true);

  useEffect(() => {
    carregarDados();
    
    const unsubscribe = NetInfo.addEventListener(state => {
      setIsOnline(state.isConnected);
      if (state.isConnected) {
        syncNow();
      }
    });
    
    return () => unsubscribe();
  }, []);

  const carregarDados = async () => {
    try {
      const usuarioData = await authService.getUsuario();
      setUsuario(usuarioData);
      
      const hojeData = await pontoService.getHoje();
      setPontosHoje(hojeData.dias || []);
      
      // Carregar estatísticas
      const extrato = await pontoService.getExtrato(new Date().toISOString().slice(0, 7));
      if (extrato.success) {
        const totalPontos = extrato.dias?.reduce((sum, d) => sum + (d.total_pontos || 0), 0) || 0;
        setStats({
          totalPontos,
          diasTrabalhados: extrato.dias?.length || 0,
          horasExtras: 0,
        });
      }
    } catch (error) {
      console.error('Erro ao carregar dados:', error);
    }
  };

  const onRefresh = async () => {
    setRefreshing(true);
    await carregarDados();
    if (isOnline) {
      await syncNow();
    }
    setRefreshing(false);
  };

  const getStatusIcon = (tipo) => {
    const icons = {
      entrada: '✅',
      saida_almoco: '🍽️',
      volta_almoco: '🔄',
      saida: '🏁',
    };
    return icons[tipo] || '⏰';
  };

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.welcome}>Olá,</Text>
          <Text style={styles.userName}>{usuario?.nome || 'Carregando...'}</Text>
          <Text style={styles.userRole}>{usuario?.cargo || 'Colaborador'}</Text>
        </View>
        <TouchableOpacity
          style={styles.perfilButton}
          onPress={() => navigation.navigate('Perfil')}
        >
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>
              {usuario?.nome?.charAt(0) || 'U'}
            </Text>
          </View>
        </TouchableOpacity>
      </View>

      {/* Status Online/Offline */}
      <View style={[styles.statusBar, isOnline ? styles.statusOnline : styles.statusOffline]}>
        <Text style={styles.statusText}>
          {isOnline ? '🟢 Online' : '🔴 Offline - Modo offline ativo'}
        </Text>
      </View>

      {/* Cards de Estatísticas */}
      <View style={styles.statsGrid}>
        <View style={styles.statCard}>
          <Text style={styles.statValue}>{stats.totalPontos}</Text>
          <Text style={styles.statLabel}>Registros no Mês</Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statValue}>{stats.diasTrabalhados}</Text>
          <Text style={styles.statLabel}>Dias Trabalhados</Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statValue}>{stats.horasExtras}h</Text>
          <Text style={styles.statLabel}>Horas Extras</Text>
        </View>
      </View>

      {/* Botão Registrar Ponto */}
      <TouchableOpacity
        style={styles.pontoButton}
        onPress={() => navigation.navigate('Ponto')}
      >
        <Text style={styles.pontoButtonText}>📱 Registrar Ponto</Text>
      </TouchableOpacity>

      {/* Pontos de Hoje */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Registros de Hoje</Text>
        {pontosHoje.length === 0 ? (
          <View style={styles.emptyState}>
            <Text style={styles.emptyStateText}>
              Nenhum registro de ponto hoje
            </Text>
          </View>
        ) : (
          pontosHoje.map((ponto, index) => (
            <View key={index} style={styles.pontoItem}>
              <Text style={styles.pontoIcon}>{getStatusIcon(ponto.tipo)}</Text>
              <View style={styles.pontoInfo}>
                <Text style={styles.pontoTipo}>
                  {ponto.tipo === 'entrada' ? 'Entrada' :
                   ponto.tipo === 'saida_almoco' ? 'Saída Almoço' :
                   ponto.tipo === 'volta_almoco' ? 'Volta Almoço' : 'Saída'}
                </Text>
                <Text style={styles.pontoHorario}>{ponto.horario}</Text>
              </View>
            </View>
          ))
        )}
      </View>

      {/* Menu Rápido */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Menu Rápido</Text>
        <View style={styles.menuGrid}>
          <TouchableOpacity
            style={styles.menuItem}
            onPress={() => navigation.navigate('Extrato')}
          >
            <Text style={styles.menuIcon}>📅</Text>
            <Text style={styles.menuText}>Extrato</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.menuItem}
            onPress={() => navigation.navigate('Solicitacoes')}
          >
            <Text style={styles.menuIcon}>📋</Text>
            <Text style={styles.menuText}>Solicitações</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.menuItem}
            onPress={() => navigation.navigate('Crachá')}
          >
            <Text style={styles.menuIcon}>🪪</Text>
            <Text style={styles.menuText}>Meu Crachá</Text>
          </TouchableOpacity>
        </View>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#667eea',
    padding: 24,
    paddingTop: 48,
    borderBottomLeftRadius: 24,
    borderBottomRightRadius: 24,
  },
  welcome: {
    fontSize: 14,
    color: 'rgba(255,255,255,0.8)',
  },
  userName: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#fff',
    marginTop: 4,
  },
  userRole: {
    fontSize: 12,
    color: 'rgba(255,255,255,0.7)',
    marginTop: 4,
  },
  perfilButton: {
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: 'rgba(255,255,255,0.2)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatar: {
    width: 46,
    height: 46,
    borderRadius: 23,
    backgroundColor: '#fff',
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#667eea',
  },
  statusBar: {
    margin: 16,
    padding: 12,
    borderRadius: 12,
    alignItems: 'center',
  },
  statusOnline: {
    backgroundColor: '#d1fae5',
  },
  statusOffline: {
    backgroundColor: '#fee2e2',
  },
  statusText: {
    fontSize: 12,
    fontWeight: '500',
  },
  statsGrid: {
    flexDirection: 'row',
    marginHorizontal: 16,
    marginTop: 8,
    gap: 12,
  },
  statCard: {
    flex: 1,
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 16,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  statValue: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#667eea',
  },
  statLabel: {
    fontSize: 12,
    color: '#666',
    marginTop: 4,
    textAlign: 'center',
  },
  pontoButton: {
    margin: 16,
    backgroundColor: '#667eea',
    borderRadius: 16,
    padding: 18,
    alignItems: 'center',
    shadowColor: '#667eea',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 4,
  },
  pontoButtonText: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#fff',
  },
  section: {
    marginHorizontal: 16,
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#333',
    marginBottom: 12,
  },
  emptyState: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 24,
    alignItems: 'center',
  },
  emptyStateText: {
    color: '#999',
  },
  pontoItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 8,
  },
  pontoIcon: {
    fontSize: 24,
    marginRight: 16,
  },
  pontoInfo: {
    flex: 1,
  },
  pontoTipo: {
    fontSize: 14,
    fontWeight: '500',
    color: '#333',
  },
  pontoHorario: {
    fontSize: 12,
    color: '#666',
    marginTop: 2,
  },
  menuGrid: {
    flexDirection: 'row',
    gap: 12,
  },
  menuItem: {
    flex: 1,
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  menuIcon: {
    fontSize: 28,
    marginBottom: 8,
  },
  menuText: {
    fontSize: 12,
    color: '#666',
  },
});