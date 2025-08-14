# 📚 Documentation Complète du Backend RenderLab

## 🎯 Vue d'Ensemble

Le backend RenderLab est une API REST construite avec Symfony 7 qui transforme automatiquement des scripts textuels en contenu multimédia riche grâce à l'intelligence artificielle. L'architecture utilise des queues asynchrones, des notifications temps réel et des services spécialisés pour chaque type de génération.

## 🏗️ Architecture du Système

```
┌─────────────────┐    HTTP/JSON    ┌─────────────────┐
│   Frontend      │ ←────────────→ │   Symfony API   │
│   (Next.js)     │                 │   (Controllers) │
└─────────────────┘                 └─────────────────┘
                                              │
                                              ▼
                                    ┌─────────────────┐
                                    │   Message Bus   │
                                    │   (Redis Queue) │
                                    └─────────────────┘
                                              │
                                              ▼
                                    ┌─────────────────┐
                                    │   Workers       │
                                    │   (Handlers)    │
                                    └─────────────────┘
                                              │
                                              ▼
                                    ┌─────────────────┐
                                    │   AI Services   │
                                    │   (Groq/Gemini) │
                                    └─────────────────┘
```

## 📁 Structure des Dossiers

```
src/
├── Controller/           # Contrôleurs API REST
├── Entity/              # Entités Doctrine (modèles de données)
├── Message/             # Messages pour les queues
├── MessageHandler/      # Handlers des messages (workers)
├── Service/             # Services métier
└── Repository/          # Repositories Doctrine
```

## 🔧 **1. Contrôleurs (Controllers)**

### **PromptController**
**Fichier :** `src/Controller/PromptController.php`
**Responsabilité :** Gestion des prompts d'images

**Routes principales :**
- `POST /api/scripts/{id}/get_prompts` - Déclenche la génération de prompts
- `GET /api/prompts/{id}` - Récupère un prompt
- `PATCH /api/prompts/{id}` - Modifie un prompt
- `DELETE /api/prompts/{id}` - Supprime un prompt
- `GET /api/tasks/{taskId}/status` - Statut d'une tâche
- `GET /api/quota/{api}` - Consultation des quotas

**Fonctionnalités :**
- Authentification JWT obligatoire
- Vérification des permissions utilisateur
- Gestion des tâches asynchrones
- Rate limiting intégré

### **ImageController**
**Fichier :** `src/Controller/ImageController.php`
**Responsabilité :** Gestion de la génération d'images

**Routes principales :**
- `POST /api/scripts/{id}/generate_images` - Déclenche la génération d'images
- `GET /api/images/{id}` - Récupère une image
- `DELETE /api/images/{id}` - Supprime une image

**Fonctionnalités :**
- Vérification de l'existence des prompts
- Gestion des tâches en cours
- Stockage sécurisé des fichiers

### **AudioController**
**Fichier :** `src/Controller/AudioController.php`
**Responsabilité :** Gestion de la génération d'audio

**Routes principales :**
- `POST /api/scripts/{id}/generate_audio` - Déclenche la génération d'audio
- `GET /api/audio/{id}` - Récupère un audio
- `DELETE /api/audio/{id}` - Supprime un audio

**Fonctionnalités :**
- Support de différentes voix
- Vérification des doublons
- Conversion automatique en MP3

### **ScriptController**
**Fichier :** `src/Controller/ScriptController.php`
**Responsabilité :** Gestion CRUD des scripts

**Routes principales :**
- `GET /api/scripts` - Liste des scripts
- `POST /api/scripts` - Créer un script
- `GET /api/scripts/{id}` - Récupérer un script
- `PATCH /api/scripts/{id}` - Modifier un script
- `DELETE /api/scripts/{id}` - Supprimer un script

### **HealthController**
**Fichier :** `src/Controller/HealthController.php`
**Responsabilité :** Monitoring et santé du système

**Routes principales :**
- `GET /api/health` - Health check complet

**Vérifications :**
- Base de données
- Redis
- APIs externes (Groq, Gemini)
- Cache
- Rate limiter

## 📦 **2. Messages (Messages)**

### **GeneratePromptsMessage**
**Fichier :** `src/Message/GeneratePromptsMessage.php`
**Responsabilité :** Message pour la génération de prompts

**Propriétés :**
- `scriptId` : ID du script à analyser
- `userId` : ID de l'utilisateur propriétaire
- `taskId` : ID unique de la tâche

### **GenerateImagesMessage**
**Fichier :** `src/Message/GenerateImagesMessage.php`
**Responsabilité :** Message pour la génération d'images

**Propriétés :**
- `scriptId` : ID du script contenant les prompts
- `userId` : ID de l'utilisateur propriétaire
- `taskId` : ID unique de la tâche

### **GenerateAudioMessage**
**Fichier :** `src/Message/GenerateAudioMessage.php`
**Responsabilité :** Message pour la génération d'audio

**Propriétés :**
- `scriptId` : ID du script à convertir
- `userId` : ID de l'utilisateur propriétaire
- `voice` : Voix sélectionnée
- `taskId` : ID unique de la tâche

## 🔄 **3. Handlers (MessageHandlers)**

### **GeneratePromptsHandler**
**Fichier :** `src/MessageHandler/GeneratePromptsHandler.php`
**Responsabilité :** Traitement des messages de génération de prompts

**Fonctionnalités :**
- Analyse des scripts via l'API Groq
- Génération de prompts multiples par scène
- Stockage en base de données
- Notifications Mercure
- Rate limiting et cache
- Retry automatique

**Workflow :**
1. Vérification des permissions
2. Récupération du script
3. Génération des prompts (avec retry)
4. Stockage en base
5. Notification du frontend

### **GenerateImagesHandler**
**Fichier :** `src/MessageHandler/GenerateImagesHandler.php`
**Responsabilité :** Traitement des messages de génération d'images

**Fonctionnalités :**
- Génération d'images via l'API Gemini
- Traitement parallèle des prompts
- Stockage sécurisé des fichiers
- Progression en temps réel
- Validation des fichiers

**Workflow :**
1. Récupération des prompts
2. Génération d'images (parallèle)
3. Stockage des fichiers
4. Mise à jour de la base
5. Notification du frontend

### **GenerateAudioHandler**
**Fichier :** `src/MessageHandler/GenerateAudioHandler.php`
**Responsabilité :** Traitement des messages de génération d'audio

**Fonctionnalités :**
- Synthèse vocale via l'API Gemini
- Conversion en MP3 via ffmpeg
- Gestion des voix multiples
- Validation des fichiers audio

**Workflow :**
1. Vérification des doublons
2. Synthèse vocale
3. Conversion MP3
4. Stockage du fichier
5. Notification du frontend

## ⚙️ **4. Services (Services)**

### **PromptGenerationService**
**Fichier :** `src/Service/PromptGenerationService.php`
**Responsabilité :** Génération de prompts via l'API Groq

**Méthodes principales :**
- `generatePrompts(string $scriptText)` : Génère des prompts pour un script
- `generatePromptsForScene(string $sceneText)` : Génère des prompts pour une scène
- `validatePrompt(string $prompt)` : Valide un prompt
- `sanitizePrompt(string $prompt)` : Sanitise un prompt

**Fonctionnalités :**
- Division automatique en scènes
- Validation du contenu
- Gestion des erreurs API
- Timeout configurable

### **ImageGenerationService**
**Fichier :** `src/Service/ImageGenerationService.php`
**Responsabilité :** Génération d'images via l'API Gemini

**Méthodes principales :**
- `generateImage(string $prompt, string $saveDir, string $filename)` : Génère une image
- `validatePrompt(string $prompt)` : Valide un prompt
- `validateGeneratedFile(string $path, string $type)` : Valide un fichier

**Fonctionnalités :**
- Validation des prompts
- Gestion sécurisée des fichiers
- Compression d'images
- Nettoyage automatique

### **AudioGenerationService**
**Fichier :** `src/Service/AudioGenerationService.php`
**Responsabilité :** Génération d'audio via l'API Gemini TTS

**Méthodes principales :**
- `generateAudio(string $text, string $voice, string $saveDir, string $filename)` : Génère un audio
- `convertPcmToWav(string $pcmPath, string $wavPath)` : Conversion PCM vers WAV
- `convertWavToMp3(string $wavPath, string $mp3Path)` : Conversion WAV vers MP3

**Fonctionnalités :**
- Synthèse vocale avancée
- Support de multiples voix
- Conversion automatique MP3
- Validation des fichiers

### **MercurePublisherService**
**Fichier :** `src/Service/MercurePublisherService.php`
**Responsabilité :** Notifications temps réel via Mercure

**Méthodes principales :**
- `publishTaskUpdate(string $taskId, array $data)` : Publie une mise à jour de tâche
- `publishScriptUpdate(int $scriptId, int $userId, array $data)` : Publie une mise à jour de script

**Topics :**
- `task/{taskId}` : Mises à jour d'une tâche spécifique
- `script/{scriptId}/user/{userId}` : Mises à jour d'un script

### **RateLimiterService**
**Fichier :** `src/Service/RateLimiterService.php`
**Responsabilité :** Limitation du taux d'utilisation des APIs

**Méthodes principales :**
- `checkLimit(string $api, int $userId)` : Vérifie les limites
- `getRemainingQuota(string $api, int $userId)` : Récupère le quota restant
- `isRateLimited(string $api, int $userId)` : Vérifie si limité

**Limites par API :**
- Groq : 100/heure, 10/minute
- Gemini : 200/heure, 20/minute
- Images : 50/heure, 5/minute
- Audio : 30/heure, 3/minute

### **CacheService**
**Fichier :** `src/Service/CacheService.php`
**Responsabilité :** Cache Redis pour optimiser les performances

**Méthodes principales :**
- `get(string $key)` : Récupère une valeur
- `set(string $key, mixed $data, int $ttl)` : Stocke une valeur
- `getOrSet(string $key, callable $callback, int $ttl)` : Cache avec fallback
- `invalidateScriptCache(int $scriptId)` : Invalide le cache d'un script

**Clés de cache :**
- `script:{id}:prompts` : Prompts d'un script
- `script:{id}:images` : Images d'un script
- `script:{id}:audio` : Audio d'un script

### **RetryService**
**Fichier :** `src/Service/RetryService.php`
**Responsabilité :** Retry automatique avec backoff exponentiel

**Méthodes principales :**
- `retryWithBackoff(callable $operation, array $context)` : Retry exponentiel
- `retryWithLinearBackoff(callable $operation, array $context)` : Retry linéaire
- `retryWithFixedDelay(callable $operation, int $delay, array $context)` : Retry à délai fixe

**Fonctionnalités :**
- Backoff exponentiel par défaut
- Jitter aléatoire
- Logs détaillés
- Context tracking

## 🗄️ **5. Entités (Entities)**

### **Script**
**Fichier :** `src/Entity/Script.php`
**Responsabilité :** Modèle de données pour les scripts

**Propriétés :**
- `id` : Identifiant unique
- `title` : Titre du script
- `content` : Contenu du script
- `createdAt` : Date de création
- `user` : Utilisateur propriétaire
- `prompts` : Collection de prompts
- `images` : Collection d'images
- `audios` : Collection d'audios

### **Task**
**Fichier :** `src/Entity/Task.php`
**Responsabilité :** Suivi des tâches asynchrones

**Propriétés :**
- `taskId` : ID unique de la tâche
- `type` : Type de tâche (prompts, images, audio)
- `status` : Statut (pending, in_progress, completed, failed)
- `scriptId` : ID du script concerné
- `userId` : ID de l'utilisateur
- `result` : Résultat de la tâche (JSON)
- `error` : Message d'erreur si échec

### **Prompt**
**Fichier :** `src/Entity/Prompt.php`
**Responsabilité :** Stockage des prompts générés

**Propriétés :**
- `id` : Identifiant unique
- `content` : Contenu des prompts (JSON)
- `script` : Script associé

### **Image**
**Fichier :** `src/Entity/Image.php`
**Responsabilité :** Stockage des images générées

**Propriétés :**
- `id` : Identifiant unique
- `url` : URLs des images (JSON)
- `script` : Script associé

### **Audio**
**Fichier :** `src/Entity/Audio.php`
**Responsabilité :** Stockage des audios générés

**Propriétés :**
- `id` : Identifiant unique
- `url` : URL de l'audio (JSON)
- `script` : Script associé

## 🔧 **6. Configuration**

### **Messenger (Queue)**
**Fichier :** `config/packages/messenger.yaml`
**Responsabilité :** Configuration des queues Redis

**Transports :**
- `async` : Redis pour les tâches asynchrones
- `failed` : Doctrine pour les tâches échouées

**Routing :**
- `GeneratePromptsMessage` → async
- `GenerateImagesMessage` → async
- `GenerateAudioMessage` → async

### **Mercure**
**Fichier :** `config/packages/mercure.yaml`
**Responsabilité :** Configuration des notifications temps réel

**Configuration :**
- URL du hub Mercure
- JWT pour l'authentification
- Permissions de publication/abonnement

### **Services**
**Fichier :** `config/services.yaml`
**Responsabilité :** Configuration des services

**Services configurés :**
- Redis Client
- Rate Limiter
- Cache Service
- Retry Service

## 🚀 **7. Workflow Complet**

### **Génération de Prompts**
1. **Frontend** → `POST /api/scripts/{id}/get_prompts`
2. **PromptController** → Vérification permissions + création tâche
3. **Message Bus** → Envoi `GeneratePromptsMessage`
4. **Worker** → `GeneratePromptsHandler`
5. **PromptGenerationService** → Appel API Groq
6. **Base de données** → Stockage des prompts
7. **Mercure** → Notification du frontend

### **Génération d'Images**
1. **Frontend** → `POST /api/scripts/{id}/generate_images`
2. **ImageController** → Vérification prompts + création tâche
3. **Message Bus** → Envoi `GenerateImagesMessage`
4. **Worker** → `GenerateImagesHandler`
5. **ImageGenerationService** → Appel API Gemini
6. **Stockage fichiers** → Sauvegarde des images
7. **Mercure** → Notification du frontend

### **Génération d'Audio**
1. **Frontend** → `POST /api/scripts/{id}/generate_audio`
2. **AudioController** → Vérification + création tâche
3. **Message Bus** → Envoi `GenerateAudioMessage`
4. **Worker** → `GenerateAudioHandler`
5. **AudioGenerationService** → Appel API Gemini TTS
6. **ffmpeg** → Conversion MP3
7. **Mercure** → Notification du frontend

## 📊 **8. Monitoring et Observabilité**

### **Health Check**
- `GET /api/health` : Vérification complète du système
- Statut de tous les services
- Métriques de performance

### **Logs**
- Logs structurés dans `var/log/`
- Traçabilité complète des opérations
- Gestion des erreurs détaillée

### **Métriques**
- Hit rate du cache Redis
- Utilisation mémoire
- Temps de réponse des APIs
- Taux de succès des tâches

## 🔒 **9. Sécurité**

### **Authentification**
- JWT obligatoire pour toutes les routes
- Vérification des permissions utilisateur
- Tokens de rafraîchissement

### **Validation**
- Sanitisation des entrées
- Validation des prompts
- Filtrage du contenu inapproprié

### **Rate Limiting**
- Limites par utilisateur
- Protection contre les abus
- Quotas configurables

## 🧪 **10. Tests**

### **Tests Unitaires**
- Services individuels
- Validation des données
- Gestion des erreurs

### **Tests d'Intégration**
- Workflows complets
- APIs externes
- Base de données

### **Tests de Performance**
- Charge des workers
- Temps de réponse
- Utilisation mémoire

## 📈 **11. Performance**

### **Optimisations**
- Cache Redis pour les résultats
- Traitement parallèle des images
- Compression des fichiers
- Retry intelligent

### **Scalabilité**
- Workers multiples
- Queue Redis distribuée
- Load balancing possible

## 🎯 **12. Maintenance**

### **Logs à Surveiller**
- `var/log/dev.log` : Logs de développement
- `var/log/prod.log` : Logs de production
- Logs des workers : `php bin/console messenger:consume -vv`

### **Commandes Utiles**
```bash
# Vérifier la santé
curl http://localhost:8000/api/health

# Voir les tâches échouées
php bin/console messenger:failed:show

# Relancer les tâches échouées
php bin/console messenger:failed:retry

# Vider le cache
php bin/console cache:clear

# Voir les routes
php bin/console debug:router
```

Cette documentation fournit une vue complète de l'architecture et du fonctionnement du backend RenderLab, facilitant la maintenance et l'évolution du code. 🚀 