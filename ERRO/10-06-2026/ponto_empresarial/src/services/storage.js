// src/services/storage.js - Armazenamento local (offline)
import AsyncStorage from '@react-native-async-storage/async-storage';

const STORAGE_KEYS = {
  PONTOS_OFFLINE: '@PontoFacil:offline:pontos',
  SOLICITACOES_OFFLINE: '@PontoFacil:offline:solicitacoes',
  CONFIG: '@PontoFacil:config',
  CACHE: '@PontoFacil:cache',
};

// Salvar ponto offline
export const savePontoOffline = async (ponto) => {
  try {
    const pontosOffline = await getPontosOffline();
    const novoPonto = {
      ...ponto,
      id_local: Date.now(),
      sincronizado: false,
      tentativas: 0,
    };
    pontosOffline.push(novoPonto);
    await AsyncStorage.setItem(STORAGE_KEYS.PONTOS_OFFLINE, JSON.stringify(pontosOffline));
    return novoPonto.id_local;
  } catch (error) {
    console.error('Erro ao salvar ponto offline:', error);
    return null;
  }
};

// Buscar pontos offline
export const getPontosOffline = async () => {
  try {
    const pontos = await AsyncStorage.getItem(STORAGE_KEYS.PONTOS_OFFLINE);
    return pontos ? JSON.parse(pontos) : [];
  } catch (error) {
    console.error('Erro ao buscar pontos offline:', error);
    return [];
  }
};

// Remover pontos sincronizados
export const removePontosSincronizados = async (ids) => {
  try {
    const pontosOffline = await getPontosOffline();
    const pontosAtualizados = pontosOffline.filter(p => !ids.includes(p.id_local));
    await AsyncStorage.setItem(STORAGE_KEYS.PONTOS_OFFLINE, JSON.stringify(pontosAtualizados));
  } catch (error) {
    console.error('Erro ao remover pontos sincronizados:', error);
  }
};

// Salvar configuração
export const saveConfig = async (key, value) => {
  try {
    const config = await getConfig();
    config[key] = value;
    await AsyncStorage.setItem(STORAGE_KEYS.CONFIG, JSON.stringify(config));
  } catch (error) {
    console.error('Erro ao salvar config:', error);
  }
};

// Buscar configuração
export const getConfig = async () => {
  try {
    const config = await AsyncStorage.getItem(STORAGE_KEYS.CONFIG);
    return config ? JSON.parse(config) : {};
  } catch (error) {
    console.error('Erro ao buscar config:', error);
    return {};
  }
};

// Salvar cache
export const saveCache = async (key, data, ttl = 3600000) => {
  try {
    const cache = await getCache();
    cache[key] = {
      data,
      expira: Date.now() + ttl,
    };
    await AsyncStorage.setItem(STORAGE_KEYS.CACHE, JSON.stringify(cache));
  } catch (error) {
    console.error('Erro ao salvar cache:', error);
  }
};

// Buscar cache
export const getCache = async () => {
  try {
    const cache = await AsyncStorage.getItem(STORAGE_KEYS.CACHE);
    return cache ? JSON.parse(cache) : {};
  } catch (error) {
    console.error('Erro ao buscar cache:', error);
    return {};
  }
};

// Limpar cache expirado
export const clearExpiredCache = async () => {
  try {
    const cache = await getCache();
    const now = Date.now();
    let altered = false;
    
    for (const key in cache) {
      if (cache[key].expira < now) {
        delete cache[key];
        altered = true;
      }
    }
    
    if (altered) {
      await AsyncStorage.setItem(STORAGE_KEYS.CACHE, JSON.stringify(cache));
    }
  } catch (error) {
    console.error('Erro ao limpar cache:', error);
  }
};