#!/bin/bash

echo "🛑 Arrêt du système de queue RenderLab..."

# Arrêter les workers Symfony
if [ -f ".worker.pid" ]; then
    WORKER_PID=$(cat .worker.pid)
    if kill -0 $WORKER_PID 2>/dev/null; then
        echo "🔄 Arrêt des workers Symfony (PID: $WORKER_PID)..."
        kill $WORKER_PID
        sleep 2
        if kill -0 $WORKER_PID 2>/dev/null; then
            echo "⚠️ Force kill des workers..."
            kill -9 $WORKER_PID
        fi
        echo "✅ Workers arrêtés"
    else
        echo "ℹ️ Workers déjà arrêtés"
    fi
    rm .worker.pid
else
    echo "ℹ️ Aucun worker en cours"
fi

# Arrêter Mercure
if [ -f ".mercure.pid" ]; then
    MERCURE_PID=$(cat .mercure.pid)
    if kill -0 $MERCURE_PID 2>/dev/null; then
        echo "📡 Arrêt de Mercure Hub (PID: $MERCURE_PID)..."
        kill $MERCURE_PID
        sleep 2
        if kill -0 $MERCURE_PID 2>/dev/null; then
            echo "⚠️ Force kill de Mercure..."
            kill -9 $MERCURE_PID
        fi
        echo "✅ Mercure arrêté"
    else
        echo "ℹ️ Mercure déjà arrêté"
    fi
    rm .mercure.pid
else
    echo "ℹ️ Aucun Mercure en cours"
fi

# Arrêter Redis (optionnel)
read -p "Voulez-vous arrêter Redis aussi? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "📦 Arrêt de Redis..."
    sudo systemctl stop redis-server
    echo "✅ Redis arrêté"
else
    echo "ℹ️ Redis laissé en cours d'exécution"
fi

echo ""
echo "🎉 Système de queue arrêté avec succès!" 