#!/bin/bash

echo "🚀 Starting deployment..."
echo ""

# Pull latest code
echo "📥 Pulling latest code from git..."
git pull
if [ $? -ne 0 ]; then
    echo "❌ Git pull failed!"
    exit 1
fi
echo "✅ Code updated"
echo ""

# Run migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force
if [ $? -ne 0 ]; then
    echo "❌ Migrations failed!"
    exit 1
fi
echo "✅ Migrations completed"
echo ""

# Clear all caches
echo "🧹 Clearing caches..."
php artisan optimize:clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan clear-compiled
echo "✅ All caches cleared"
echo ""

echo "🎉 Deployment completed successfully!"
