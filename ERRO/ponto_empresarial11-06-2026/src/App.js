// src/App.js
import React, { useEffect, useState } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { ActivityIndicator, View } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

// Importar telas
import LoginScreen from './screens/LoginScreen';
import DashboardScreen from './screens/DashboardScreen';
import PontoScreen from './screens/PontoScreen';
import ExtratoScreen from './screens/ExtratoScreen';
import SolicitacoesScreen from './screens/SolicitacoesScreen';
import NovaSolicitacaoScreen from './screens/NovaSolicitacaoScreen';
import PerfilScreen from './screens/PerfilScreen';
import CrachaScreen from './screens/CrachaScreen';

import { startAutoSync, stopAutoSync } from './services/sync';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

// Tab Navigator principal
function MainTabs() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarIcon: ({ focused, color, size }) => {
          let iconName;
          if (route.name === 'Início') iconName = '🏠';
          else if (route.name === 'Ponto') iconName = '⏰';
          else if (route.name === 'Extrato') iconName = '📅';
          else if (route.name === 'Solicitações') iconName = '📋';
          else if (route.name === 'Perfil') iconName = '👤';
          return <Text style={{ fontSize: size }}>{iconName}</Text>;
        },
        tabBarActiveTintColor: '#667eea',
        tabBarInactiveTintColor: 'gray',
        headerShown: false,
      })}
    >
      <Tab.Screen name="Início" component={DashboardScreen} />
      <Tab.Screen name="Ponto" component={PontoScreen} />
      <Tab.Screen name="Extrato" component={ExtratoScreen} />
      <Tab.Screen name="Solicitações" component={SolicitacoesScreen} />
      <Tab.Screen name="Perfil" component={PerfilScreen} />
    </Tab.Navigator>
  );
}

export default function App() {
  const [isLoading, setIsLoading] = useState(true);
  const [isLoggedIn, setIsLoggedIn] = useState(false);

  useEffect(() => {
    checkLoginStatus();
    startAutoSync();
    
    return () => {
      stopAutoSync();
    };
  }, []);

  const checkLoginStatus = async () => {
    try {
      const token = await AsyncStorage.getItem('@PontoFacil:token');
      setIsLoggedIn(!!token);
    } catch (error) {
      console.error('Erro ao verificar login:', error);
    } finally {
      setIsLoading(false);
    }
  };

  if (isLoading) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color="#667eea" />
      </View>
    );
  }

  return (
    <NavigationContainer>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {!isLoggedIn ? (
          <Stack.Screen name="Login" component={LoginScreen} />
        ) : (
          <>
            <Stack.Screen name="Main" component={MainTabs} />
            <Stack.Screen name="NovaSolicitacao" component={NovaSolicitacaoScreen} />
            <Stack.Screen name="Crachá" component={CrachaScreen} />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}