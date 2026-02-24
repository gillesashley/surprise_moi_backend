# Surprise Moi - Technical Documentation

Welcome to the Surprise Moi technical documentation. This comprehensive guide will help you understand and contribute to the codebase effectively.

## What is Surprise Moi?

Surprise Moi is a multi-vendor e-commerce platform that connects customers with vendors offering products and services. The platform includes a sophisticated referral system, vendor onboarding process, real-time chat, payment integration, and multi-role dashboards.

## Documentation Structure

This documentation is organized by feature domains to make navigation intuitive:

### Core Documentation

1. **[Architecture Overview](./01-architecture.md)**
    - Technology stack and design patterns
    - Project structure and organization
    - Data flow and request lifecycle

2. **[Getting Started Guide](./02-getting-started.md)**
    - Development environment setup
    - Running the application
    - Common workflows

### Feature Domains

3. **[Authentication & Users](./03-authentication-users.md)**
    - User registration and login
    - Role-based access control
    - Profile management
    - OTP verification system

4. **[E-commerce System](./04-ecommerce.md)**
    - Products and services
    - Shopping cart
    - Orders and fulfillment
    - Reviews and ratings

5. **[Payment & Financial Systems](./05-payments-finance.md)**
    - Paystack integration
    - Order payments
    - Vendor balance tracking
    - Payout requests and processing

6. **[Vendor System](./06-vendor-system.md)**
    - Vendor registration and onboarding
    - Shop management
    - Vendor analytics
    - Document verification

7. **[Chat & Real-time Features](./07-chat-realtime.md)**
    - Conversation system
    - Real-time messaging with Laravel Reverb
    - Broadcasting events
    - Typing indicators

8. **[Referral & Target Systems](./08-referrals-targets.md)**
    - Referral code management
    - Influencer, field agent, and marketer roles
    - Target tracking and achievements
    - Earnings and payouts

9. **[API Reference](./09-api-reference.md)**
    - API structure and versioning
    - Authentication
    - Common patterns
    - Error handling

### Additional Resources

10. **[Database Schema](./10-database-schema.md)**
    - Entity relationship overview
    - Key tables and relationships
    - Migration guide

11. **[Services & Utilities](./11-services-utilities.md)**
    - Service layer architecture
    - Payment services
    - Third-party integrations
    - Helper utilities

12. **[Testing Guide](./12-testing.md)**
    - Testing philosophy
    - Writing tests
    - Running test suites

## Quick Links

### For New Developers

Start with the [Getting Started Guide](./02-getting-started.md) to set up your environment, then read the [Architecture Overview](./01-architecture.md) to understand the system design.

### For Feature Development

Navigate to the relevant feature domain documentation to understand the existing implementation before making changes.

### For API Integration

Check the [API Reference](./09-api-reference.md) for endpoint documentation and authentication patterns.

## Technology Stack

- **Backend**: Laravel 12 (PHP 8.2)
- **Frontend**: React 19 + TypeScript + Inertia.js 2
- **Database**: PostgreSQL
- **Real-time**: Laravel Reverb (WebSocket server)
- **Authentication**: Laravel Sanctum + Fortify
- **Payments**: Paystack
- **Styling**: Tailwind CSS 4
- **Build Tool**: Vite

## Key Concepts

### Multi-Vendor Platform

The platform supports multiple vendors, each with their own shops, products, and services. Vendors go through a verification process before being approved.

### Role-Based System

Users can have different roles: `customer`, `vendor`, `admin`, `super_admin`, `influencer`, `field_agent`, and `marketer`. Each role has specific permissions and dashboards.

### Dual API Strategy

The application provides both REST API endpoints (`/api/v1`) and Inertia.js server-side rendering (`/dashboard`) for different use cases.

### Real-time Communication

Chat between customers and vendors is powered by Laravel Reverb, providing instant message delivery and presence indicators.

## Contributing

When contributing to this project:

1. Read the relevant feature documentation thoroughly
2. Follow Laravel best practices and conventions
3. Write tests for new features
4. Update documentation when adding features
5. Use meaningful commit messages

## Getting Help

- Check the specific feature documentation for detailed implementation details
- Review existing code in similar areas for patterns and conventions
- Consult the API reference for endpoint specifications

---

**Last Updated**: February 2026
