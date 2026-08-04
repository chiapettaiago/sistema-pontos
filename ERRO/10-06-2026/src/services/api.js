// src/services/api.js - Configuração da API
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

// Configuração base da API
const API_BASE_URL = 'http://192.168.1.100/ponto_empresarial/api/'; // Altere para IP do seu servidor

const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Interceptor para adicionar token
api.interceptors.request.use(
  async (config) => {
    const token = await AsyncStorage.getItem('@PontoFacil:token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Interceptor para tratar erros
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // Token expirado ou inválido
      await AsyncStorage.multiRemove(['@PontoFacil:token', '@PontoFacil:usuario']);
      // Redirecionar para login
      // NavigationService.navigate('Login');
    }
    return Promise.reject(error);
  }
);

// Serviços de autenticação
export const authService = {
  login: async (email, senha) => {
    const response = await api.post('auth.php', { email, senha });
    if (response.data.success) {
      await AsyncStorage.setItem('@PontoFacil:token', response.data.token);
      await AsyncStorage.setItem('@PontoFacil:usuario', JSON.stringify(response.data.funcionario));
    }
    return response.data;
  },
  
  logout: async () => {
    await AsyncStorage.multiRemove(['@PontoFacil:token', '@PontoFacil:usuario']);
  },
  
  getUsuario: async () => {
    const usuario = await AsyncStorage.getItem('@PontoFacil:usuario');
    return usuario ? JSON.parse(usuario) : null;
  },
  
  getToken: async () => {
    return await AsyncStorage.getItem('@PontoFacil:token');
  },
};

// Serviços de ponto
export const pontoService = {
  registrar: async (tipo, latitude, longitude) => {
    const response = await api.post('ponto.php', { tipo, latitude, longitude });
    return response.data;
  },
  
  getExtrato: async (mes) => {
    const response = await api.get(`extrato.php?mes=${mes}`);
    return response.data;
  },
  
  getHoje: async () => {
    const response = await api.get('ponto_hoje.php');
    return response.data;
  },
  
  sincronizarOffline: async (pontosOffline) => {
    const response = await api.post('sincronizar.php', { pontos: pontosOffline });
    return response.data;
  },
};

// Serviços de funcionário
export const funcionarioService = {
  getPerfil: async () => {
    const response = await api.get('funcionario.php');
    return response.data;
  },
  
  updatePerfil: async (dados) => {
    const response = await api.put('funcionario.php', dados);
    return response.data;
  },
};

// Serviços de solicitações
export const solicitacaoService = {
  listar: async (status = null) => {
    const url = status ? `solicitacoes.php?status=${status}` : 'solicitacoes.php';
    const response = await api.get(url);
    return response.data;
  },
  
  criar: async (dados) => {
    const response = await api.post('solicitacoes.php', dados);
    return response.data;
  },
  
  cancelar: async (id) => {
    const response = await api.delete(`solicitacoes.php?id=${id}`);
    return response.data;
  },
};

export default api;