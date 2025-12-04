// Firebase config
const firebaseConfig = {
  apiKey: "AIzaSyDs6eEpYkKzOIbit60mitGDY6qbLMclxvs",
  authDomain: "esp32-connecttest.firebaseapp.com",
  databaseURL: "https://esp32-connecttest-default-rtdb.asia-southeast1.firebasedatabase.app",
  projectId: "esp32-connecttest",
  storageBucket: "esp32-connecttest.firebasestorage.app",
  messagingSenderId: "950000610308",
  appId: "1:950000610308:web:a39583249e23784128d951"
};

// Initialize Firebase
const app = firebase.initializeApp(firebaseConfig);
const database = firebase.database();
