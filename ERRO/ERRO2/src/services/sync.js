// src/services/sync.js - Sincronização offline
import NetInfo from '@react-native-community/netinfo';
import { getPontosOffline, removePontosSincronizados, savePontoOffline } from './storage';
import { pontoService } from './api';

let syncInterval = null;

// Iniciar sincronização automática
export const startAutoSync = () => {
  if (syncInterval) clearInterval(syncInterval);
  
  // Sincronizar a cada 5 minutos quando online
  syncInterval = setInterval(async () => {
    const netInfo = await NetInfo.fetch();
    if (netInfo.isConnected) {
      await syncPendingPoints();
    }
  }, 300000); // 5 minutos
};

// Parar sincronização
export const stopAutoSync = () => {
  if (syncInterval) {
    clearInterval(syncInterval);
    syncInterval = null;
  }
};

// Sincronizar pontos pendentes
export const syncPendingPoints = async () => {
  try {
    const pontosOffline = await getPontosOffline();
    const pendentes = pontosOffline.filter(p => !p.sincronizado);
    
    if (pendentes.length === 0) return { sincronizados: 0 };
    
    // Agrupar pontos por tentativas
    const pontosParaSincronizar = pendentes.filter(p => p.tentativas < 3);
    
    if (pontosParaSincronizar.length === 0) return { sincronizados: 0 };
    
    const response = await pontoService.sincronizarOffline(pontosParaSincronizar);
    
    if (response.success) {
      const idsSincronizados = pontosParaSincronizar.map(p => p.id_local);
      await removePontosSincronizados(idsSincronizados);
      return { sincronizados: response.processados };
    }
    
    return { sincronizados: 0 };
  } catch (error) {
    console.error('Erro na sincronização:', error);
    return { sincronizados: 0, erro: error.message };
  }
};

// Tentar sincronizar imediatamente
export const syncNow = async () => {
  const netInfo = await NetInfo.fetch();
  if (netInfo.isConnected) {
    return await syncPendingPoints();
  }
  return { sincronizados: 0, offline: true };
};