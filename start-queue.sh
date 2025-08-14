#!/bin/bash

echo "🚀 Démarrage du système de queue RenderLab..."

# Vérifier que Redis est installé
if ! command -v redis-server &> /dev/null; then
    echo "❌ Redis n'est pas installé. Installez-le avec: sudo apt-get install redis-server"
    exit 1
fi

# Vérifier que ffmpeg est installé
if ! command -v ffmpeg &> /dev/null; then
    echo "❌ ffmpeg n'est pas installé. Installez-le avec: sudo apt-get install ffmpeg"
    exit 1
fi

# Démarrer Redis
echo "📦 Démarrage de Redis..."
sudo systemctl start redis-server
if [ $? -eq 0 ]; then
    echo "✅ Redis démarré"
else
    echo "❌ Erreur lors du démarrage de Redis"
    exit 1
fi

# Vérifier que Redis fonctionne
if redis-cli ping | grep -q "PONG"; then
    echo "✅ Redis répond correctement"
else
    echo "❌ Redis ne répond pas"
    exit 1
fi

# Vérifier que Mercure existe
if [ ! -f "./mercure" ]; then
    echo "📥 Téléchargement de Mercure..."
    wget https://github.com/dunglas/mercure/releases/download/v0.15.4/mercure_0.15.4_Linux_x86_64.tar.gz
    tar -xzf mercure_0.15.4_Linux_x86_64.tar.gz
    rm mercure_0.15.4_Linux_x86_64.tar.gz
    chmod +x mercure
fi

# Créer la configuration Mercure si elle n'existe pas
if [ ! -f "./mercure.yaml" ]; then
    echo "⚙️ Création de la configuration Mercure..."
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
fi

# Démarrer Mercure en arrière-plan
echo "📡 Démarrage de Mercure Hub..."
./mercure run --config mercure.yaml &
MERCURE_PID=$!
echo "✅ Mercure démarré (PID: $MERCURE_PID)"

# Attendre que Mercure soit prêt
sleep 2

# Vérifier que Mercure répond
if curl -s http://localhost:3000/.well-known/mercure > /dev/null; then
    echo "✅ Mercure répond correctement"
else
    echo "❌ Mercure ne répond pas"
    kill $MERCURE_PID 2>/dev/null
    exit 1
fi

# Démarrer les workers Symfony
echo "🔄 Démarrage des workers Symfony..."
php bin/console messenger:consume async --time-limit=3600 --memory-limit=128M &
WORKER_PID=$!
echo "✅ Workers démarrés (PID: $WORKER_PID)"

# Sauvegarder les PIDs pour arrêt propre
echo $MERCURE_PID > .mercure.pid
echo $WORKER_PID > .worker.pid

echo ""
echo "🎉 Système de queue démarré avec succès!"
echo ""
echo "📊 Services actifs:"
echo "  - Redis: $(redis-cli ping)"
echo "  - Mercure: http://localhost:3000/.well-known/mercure"
echo "  - Workers: PID $WORKER_PID"
echo ""
echo "🛑 Pour arrêter: ./stop-queue.sh"
echo "📝 Logs: tail -f var/log/dev.log"
echo ""
echo "🚀 Vous pouvez maintenant démarrer l'application Symfony:"
echo "   symfony server:start" 