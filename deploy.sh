#!/bin/bash

# EPROFOS Needs Analysis System Deployment Script
# This script automates the deployment of the needs analysis system

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="eprofos-needs-analysis"
DOCKER_COMPOSE_FILE="docker-compose.yml"
PHP_CONTAINER="php"
DB_CONTAINER="postgres"

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_requirements() {
    log_info "Checking system requirements..."
    
    # Check if Docker is installed
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    # Check if Docker Compose is installed
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi
    
    # Check if .env file exists
    if [ ! -f .env ]; then
        log_error ".env file not found. Please create it from .env.example"
        exit 1
    fi
    
    log_success "All requirements met"
}

build_containers() {
    log_info "Building Docker containers..."
    docker-compose build --no-cache
    log_success "Containers built successfully"
}

start_services() {
    log_info "Starting services..."
    docker-compose up -d
    
    # Wait for database to be ready
    log_info "Waiting for database to be ready..."
    sleep 10
    
    # Check if containers are running
    if ! docker-compose ps | grep -q "Up"; then
        log_error "Some containers failed to start"
        docker-compose logs
        exit 1
    fi
    
    log_success "Services started successfully"
}

install_dependencies() {
    log_info "Installing PHP dependencies..."
    docker-compose exec -T $PHP_CONTAINER composer install --no-dev --optimize-autoloader
    log_success "Dependencies installed"
}

setup_database() {
    log_info "Setting up database..."
    
    # Create database if it doesn't exist
    docker-compose exec -T $PHP_CONTAINER php bin/console doctrine:database:create --if-not-exists
    
    # Run migrations
    docker-compose exec -T $PHP_CONTAINER php bin/console doctrine:migrations:migrate --no-interaction
    
    log_success "Database setup completed"
}

clear_cache() {
    log_info "Clearing application cache..."
    docker-compose exec -T $PHP_CONTAINER php bin/console cache:clear --env=prod
    docker-compose exec -T $PHP_CONTAINER php bin/console cache:warmup --env=prod
    log_success "Cache cleared and warmed up"
}

set_permissions() {
    log_info "Setting file permissions..."
    docker-compose exec -T $PHP_CONTAINER chown -R www-data:www-data /var/www/html/var
    docker-compose exec -T $PHP_CONTAINER chmod -R 755 /var/www/html/var
    log_success "Permissions set"
}

run_tests() {
    log_info "Running tests..."
    
    # Run PHPUnit tests
    if docker-compose exec -T $PHP_CONTAINER php bin/phpunit --testsuite=functional; then
        log_success "All tests passed"
    else
        log_warning "Some tests failed. Check the output above."
        read -p "Do you want to continue deployment? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log_error "Deployment aborted"
            exit 1
        fi
    fi
}

setup_cron_jobs() {
    log_info "Setting up cron jobs for automated tasks..."
    
    # Create cron job for expiring old requests (daily at 2 AM)
    echo "0 2 * * * docker-compose exec -T $PHP_CONTAINER php bin/console app:needs-analysis:expire" | crontab -
    
    # Create cron jobs for reminders (daily at 9 AM)
    echo "0 9 * * * docker-compose exec -T $PHP_CONTAINER php bin/console app:needs-analysis:remind --days-before-expiry=7" | crontab -
    echo "0 9 * * * docker-compose exec -T $PHP_CONTAINER php bin/console app:needs-analysis:remind --days-before-expiry=3" | crontab -
    echo "0 9 * * * docker-compose exec -T $PHP_CONTAINER php bin/console app:needs-analysis:remind --days-before-expiry=1" | crontab -
    
    log_success "Cron jobs configured"
}

create_admin_user() {
    log_info "Creating admin user..."
    
    read -p "Enter admin email: " admin_email
    read -s -p "Enter admin password: " admin_password
    echo
    
    # Create admin user (assuming you have a command for this)
    docker-compose exec -T $PHP_CONTAINER php bin/console app:create-admin "$admin_email" "$admin_password"
    
    log_success "Admin user created"
}

backup_database() {
    log_info "Creating database backup..."
    
    backup_dir="backups"
    mkdir -p $backup_dir
    
    backup_file="$backup_dir/needs_analysis_$(date +%Y%m%d_%H%M%S).sql"
    
    docker-compose exec -T $DB_CONTAINER pg_dump -U app app > $backup_file
    
    log_success "Database backup created: $backup_file"
}

show_deployment_info() {
    log_success "Deployment completed successfully!"
    echo
    echo "=== DEPLOYMENT INFORMATION ==="
    echo "Project: $PROJECT_NAME"
    echo "Environment: Production"
    echo "Date: $(date)"
    echo
    echo "=== SERVICES ==="
    docker-compose ps
    echo
    echo "=== ACCESS INFORMATION ==="
    echo "Admin Interface: http://localhost/admin/needs-analysis"
    echo "Public Forms: http://localhost/public/needs-analysis/{token}/form/{type}"
    echo
    echo "=== AUTOMATED TASKS ==="
    echo "- Expiration check: Daily at 2:00 AM"
    echo "- Reminders: Daily at 9:00 AM (7, 3, 1 days before expiry)"
    echo
    echo "=== MONITORING ==="
    echo "- Check logs: docker-compose logs -f"
    echo "- Check status: docker-compose ps"
    echo "- Access container: docker-compose exec $PHP_CONTAINER bash"
    echo
    echo "=== MAINTENANCE ==="
    echo "- Backup database: ./deploy.sh backup"
    echo "- Update system: ./deploy.sh update"
    echo "- Restart services: docker-compose restart"
}

# Main deployment function
deploy() {
    log_info "Starting deployment of $PROJECT_NAME..."
    
    check_requirements
    build_containers
    start_services
    install_dependencies
    setup_database
    clear_cache
    set_permissions
    
    # Ask if user wants to run tests
    read -p "Do you want to run tests? (Y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Nn]$ ]]; then
        run_tests
    fi
    
    setup_cron_jobs
    
    # Ask if user wants to create admin user
    read -p "Do you want to create an admin user? (Y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Nn]$ ]]; then
        create_admin_user
    fi
    
    backup_database
    show_deployment_info
}

# Update function
update() {
    log_info "Updating $PROJECT_NAME..."
    
    # Pull latest changes
    git pull origin main
    
    # Rebuild containers
    build_containers
    
    # Update dependencies
    install_dependencies
    
    # Run migrations
    docker-compose exec -T $PHP_CONTAINER php bin/console doctrine:migrations:migrate --no-interaction
    
    # Clear cache
    clear_cache
    
    # Restart services
    docker-compose restart
    
    log_success "Update completed successfully!"
}

# Backup function
backup() {
    backup_database
}

# Restore function
restore() {
    if [ -z "$1" ]; then
        log_error "Please provide backup file path"
        echo "Usage: ./deploy.sh restore <backup_file>"
        exit 1
    fi
    
    if [ ! -f "$1" ]; then
        log_error "Backup file not found: $1"
        exit 1
    fi
    
    log_warning "This will restore the database from backup. All current data will be lost!"
    read -p "Are you sure you want to continue? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_info "Restore cancelled"
        exit 0
    fi
    
    log_info "Restoring database from $1..."
    docker-compose exec -T $DB_CONTAINER psql -U app app < "$1"
    log_success "Database restored successfully"
}

# Status function
status() {
    echo "=== CONTAINER STATUS ==="
    docker-compose ps
    echo
    echo "=== RECENT LOGS ==="
    docker-compose logs --tail=20
}

# Help function
show_help() {
    echo "EPROFOS Needs Analysis System Deployment Script"
    echo
    echo "Usage: ./deploy.sh [COMMAND]"
    echo
    echo "Commands:"
    echo "  deploy    Full deployment (default)"
    echo "  update    Update existing deployment"
    echo "  backup    Create database backup"
    echo "  restore   Restore database from backup"
    echo "  status    Show system status"
    echo "  help      Show this help message"
    echo
    echo "Examples:"
    echo "  ./deploy.sh                           # Full deployment"
    echo "  ./deploy.sh update                    # Update system"
    echo "  ./deploy.sh backup                    # Create backup"
    echo "  ./deploy.sh restore backup.sql       # Restore from backup"
    echo "  ./deploy.sh status                    # Check status"
}

# Main script logic
case "${1:-deploy}" in
    "deploy")
        deploy
        ;;
    "update")
        update
        ;;
    "backup")
        backup
        ;;
    "restore")
        restore "$2"
        ;;
    "status")
        status
        ;;
    "help"|"-h"|"--help")
        show_help
        ;;
    *)
        log_error "Unknown command: $1"
        show_help
        exit 1
        ;;
esac