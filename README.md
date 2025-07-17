# 🍕 FoodHub Laravel API

A comprehensive food delivery and restaurant management API built with Laravel 12 and PostgreSQL, designed for the Saudi market.

![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15+-4169E1?style=for-the-badge&logo=postgresql)
![Redis](https://img.shields.io/badge/Redis-7.0+-DC382D?style=for-the-badge&logo=redis)

## 🎯 Project Overview

FoodHub API serves as the central hub for all business logic, data management, and integrations. It provides:

- **Multi-tenant restaurant management** with branch-specific operations
- **Customer authentication** with loyalty programs and rewards
- **Comprehensive order management** from placement to delivery
- **Real-time delivery tracking** with driver management
- **Advanced menu management** with nutritional information and customizations
- **Integrated payment processing** with multiple payment methods
- **Geospatial features** for location-based services

## 🏗️ Architecture

### Core Components

- **Restaurants & Branches**: Multi-location restaurant management
- **Users & Customers**: Separate authentication systems for staff and customers
- **Orders & Payments**: Complete order lifecycle with payment processing
- **Menu Management**: Categories, items, and branch-specific availability
- **Loyalty Programs**: Points, stamps, tiers, and challenges
- **Delivery System**: Driver management with real-time tracking
- **Analytics**: Business intelligence and reporting

### Technology Stack

- **Backend**: Laravel 12 with PHP 8.3+
- **Database**: PostgreSQL with JSONB and geospatial support
- **Cache/Queue**: Redis for performance optimization
- **Authentication**: Laravel Sanctum for API token management
- **Search**: Full-text search with PostgreSQL GIN indexes
- **File Storage**: AWS S3 compatible storage

## 🚀 Features

### ✅ Sprint 2 Completed (Current)
- **Database Architecture**: 24+ optimized migrations with proper indexing
- **Eloquent Models**: Complete relationship mapping and business logic
- **RBAC System**: 7-role hierarchy (Super Admin to Customer Service)
- **Geospatial Support**: Location-based queries for restaurants and delivery
- **Loyalty Foundation**: Flexible points, stamps, and challenges system
- **Test Data**: Real Saudi restaurant data for development

### 🔜 Upcoming Sprints
- **API Routes & Controllers**: RESTful endpoints with proper validation
- **Authentication & Authorization**: Complete security implementation
- **Payment Integration**: MADA, Apple Pay, Google Pay support
- **POS Integration**: Square and Toast POS system connectivity
- **External Services**: Google Maps and AI/ML service integration

## 🗄️ Database Schema

### Core Tables
- `restaurants` - Restaurant entities with business information
- `restaurant_branches` - Location-specific operations and settings
- `users` - Restaurant staff with role-based permissions
- `customers` - Customer accounts with authentication
- `orders` & `order_items` - Complete order management
- `menu_categories` & `menu_items` - Hierarchical menu structure

### Advanced Features
- **PostgreSQL JSONB**: Flexible settings and preferences storage
- **Geospatial Indexes**: Fast location-based queries
- **Full-text Search**: Advanced search across restaurants and menus
- **Composite Indexes**: Optimized for frequent query patterns

## 🛠️ Installation

### Prerequisites
- PHP 8.3+
- PostgreSQL 15+
- Redis 7.0+
- Composer

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/Bare3-dev/FoodHub.git
   cd FoodHub
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database configuration**
   - Create PostgreSQL database: `foodhub_api`
   - Update `.env` with your database credentials
   
5. **Run migrations and seeders**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Start development server**
   ```bash
   php artisan serve
   ```

## 📊 Sample Data

The seeder includes realistic Saudi market data:
- **Al Baik**: Famous for crispy fried chicken
- **Kudu**: Popular burger chain
- **Mama Noura**: Traditional Saudi cuisine
- **Pizza Hut Saudi**: International with local adaptations

## 🔧 Development Standards

- **PSR-12** coding standards
- **Strict typing** (`declare(strict_types=1)`)
- **Final classes** to prevent inheritance
- **Comprehensive PHPDoc** documentation
- **Repository & Service patterns** for clean architecture

## 🔒 Security Features

- **Laravel Sanctum** for API authentication
- **Role-based access control** (RBAC)
- **Input validation** and sanitization
- **Rate limiting** for API protection
- **Encrypted sensitive data** storage

## 📚 API Documentation

- **OpenAPI/Swagger** specification
- **Postman collections** for testing
- **Comprehensive error handling** with standardized responses
- **API versioning** for backward compatibility

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🏪 Saudi Market Focus

Designed specifically for the Saudi Arabian market with:
- **Arabic language support**
- **Local payment methods** (MADA)
- **Cultural considerations** in design
- **Realistic Saudi restaurant data**
- **Local business practices** integration

---

<p align="center">Built with ❤️ for the Saudi food delivery ecosystem</p>
