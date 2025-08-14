# 🚀 Système de Queue RenderLab

## 📋 Vue d'ensemble

Ce système implémente des queues asynchrones avec Redis et des notifications temps réel avec Mercure pour optimiser les opérations IA longues.

## 🏗️ Architecture

```
Frontend → API → Message Bus → Redis Queue → Workers → Mercure Hub → Frontend
```

## 📦 Composants

### Messages
- `GeneratePromptsMessage` - Génération de prompts via Groq
- `GenerateImagesMessage` - Génération d'images via Gemini
- `GenerateAudioMessage` - Génération d'audio via Gemini

### Handlers
- `GeneratePromptsHandler` - Traite la génération de prompts
- `GenerateImagesHandler` - Traite la génération d'images
- `GenerateAudioHandler` - Traite la génération d'audio

### Services
- `MercurePublisherService` - Publie les notifications temps réel

## 🚀 Installation et Démarrage

### 1. Prérequis
```bash
# Installer Redis
sudo apt-get install redis-server

# Installer ffmpeg (pour l'audio)
sudo apt-get install ffmpeg
```

### 2. Configuration Redis
```bash
# Démarrer Redis
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Vérifier que Redis fonctionne
redis-cli ping
# Réponse: PONG
```

### 3. Configuration Mercure
```bash
# Télécharger le hub Mercure
wget https://github.com/dunglas/mercure/releases/download/v0.15.4/mercure_0.15.4_Linux_x86_64.tar.gz
tar -xzf mercure_0.15.4_Linux_x86_64.tar.gz

# Créer le fichier de configuration
cat > mercure.yaml << EOF
addr: ":3000"
cors_allowed_origins:
  - "http://localhost:3000"
  - "http://127.0.0.1:3000"
publish_allowed_origins:
  - "http://localhost:3000"
  - "http://127.0.0.1:3000"
jwt:
  key: "ChangeThisMercureHubJWTSecretKey"
EOF
```

### 4. Variables d'Environnement
Ajouter dans `.env.local`:
```env
###> symfony/messenger ###
MESSENGER_TRANSPORT_DSN=redis://localhost:6379/messenger
###< symfony/messenger ###

###> symfony/mercure-bundle ###
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=ChangeThisMercureHubJWTSecretKey
###< symfony/mercure-bundle ###

###> AI API Keys ###
GROQ_API_KEY=your_groq_api_key_here
GEMINI_API_KEY=your_gemini_api_key_here
###< AI API Keys ###
```

## 🏃‍♂️ Démarrage des Services

### 1. Démarrer Redis
```bash
sudo systemctl start redis-server
```

### 2. Démarrer Mercure Hub
```bash
./mercure run --config mercure.yaml
```

### 3. Démarrer les Workers Symfony
```bash
# Dans un terminal séparé
php bin/console messenger:consume async -vv

# Ou en mode daemon (production)
php bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
```

### 4. Démarrer l'Application Symfony
```bash
symfony server:start
```

## 📡 API Endpoints

### Génération de Prompts
```bash
POST /api/scripts/{id}/get_prompts
Response: {"success": true, "task_id": "prompts_xxx", "status": "pending"}
```

### Génération d'Images
```bash
POST /api/scripts/{id}/generate_images
Response: {"success": true, "task_id": "images_xxx", "status": "pending"}
```

### Génération d'Audio
```bash
POST /api/scripts/{id}/generate_audio
Body: {"voice": "default"}
Response: {"success": true, "task_id": "audio_xxx", "status": "pending"}
```

### Statut des Tâches
```bash
GET /api/tasks/{taskId}/status
Response: {
  "task_id": "prompts_xxx",
  "type": "prompts",
  "status": "completed",
  "result": {"scenes": 3, "total_prompts": 9}
}
```

## 🔄 Notifications Mercure

### Topics
- `task/{taskId}` - Mises à jour d'une tâche spécifique
- `script/{scriptId}/user/{userId}` - Mises à jour d'un script

### Format des Messages
```json
{
  "status": "in_progress|completed|failed",
  "message": "Description de l'action",
  "progress": {
    "current": 5,
    "total": 10,
    "message": "Generating image 5/10..."
  },
  "result": {
    "scenes": 3,
    "total_prompts": 9
  },
  "error": "Message d'erreur si échec"
}
```

## 🎯 Utilisation Frontend

### 1. S'abonner aux Notifications
```javascript
const eventSource = new EventSource('http://localhost:3000/.well-known/mercure?topic=task/prompts_xxx');

eventSource.onmessage = (event) => {
  const data = JSON.parse(event.data);
  console.log('Task update:', data);
  
  if (data.status === 'completed') {
    // Tâche terminée, rafraîchir l'interface
    refreshInterface();
  }
};
```

### 2. Polling de Statut (Alternative)
```javascript
async function checkTaskStatus(taskId) {
  const response = await fetch(`/api/tasks/${taskId}/status`);
  const data = await response.json();
  
  if (data.status === 'completed') {
    // Tâche terminée
    return data.result;
  } else if (data.status === 'failed') {
    // Gérer l'erreur
    throw new Error(data.error);
  }
  
  // Continuer le polling
  setTimeout(() => checkTaskStatus(taskId), 2000);
}
```

## 🔧 Monitoring et Debug

### 1. Vérifier les Queues Redis
```bash
redis-cli
> LLEN messenger
> LRANGE messenger 0 -1
```

### 2. Logs des Workers
```bash
php bin/console messenger:consume async -vv
```

### 3. Messages Échoués
```bash
php bin/console messenger:failed:show
php bin/console messenger:failed:retry
```

## 🚨 Gestion des Erreurs

### 1. Timeout des APIs IA
- Les handlers gèrent automatiquement les timeouts
- Retry automatique configuré dans Messenger
- Notifications d'erreur via Mercure

### 2. Rate Limiting
- Implémenter un rate limiter sur les endpoints
- Utiliser Redis pour le rate limiting
- Queue les requêtes dépassant les limites

### 3. Monitoring
- Logs détaillés dans les handlers
- Métriques de performance
- Alertes en cas d'échec répété

## 📈 Performance

### Optimisations
- Traitement parallèle des images
- Cache Redis pour les résultats
- Compression des notifications Mercure
- Workers multiples pour la scalabilité

### Métriques
- Temps de traitement moyen
- Taux de succès
- Utilisation mémoire/CPU
- Latence des APIs externes

## 🔒 Sécurité

### Authentification
- Vérification des permissions utilisateur
- Validation des données d'entrée
- Sanitisation des prompts

### Rate Limiting
- Limite par utilisateur
- Limite par IP
- Cooldown entre les requêtes

## 🧪 Tests

### Tests Unitaires
```bash
php bin/phpunit tests/MessageHandler/
```

### Tests d'Intégration
```bash
php bin/phpunit tests/Controller/
```

### Tests de Performance
```bash
# Simuler 100 requêtes/minute
ab -n 100 -c 10 http://localhost:8000/api/scripts/1/get_prompts
``` 