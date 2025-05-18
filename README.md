# The Bohemian Burrows - Point of Sale System

A comprehensive Point of Sale (POS) system for clothing retail businesses, featuring inventory management, admin and cashier panels, multiple payment method support, and fully responsive design for all devices.

## Features

- **User Authentication**: Separate login for admin, cashier, and customer users
- **Responsive Design**: Fully mobile-friendly interface that works on phones, tablets, and desktops
- **Dashboard**: Overview of key metrics and quick navigation
- **Inventory Management**: Track stock levels and manage products
- **Point of Sale (POS)**: User-friendly interface for processing sales
- **Multiple Payment Methods**: Support for cash, card, GCash, and PayMaya
- **Online Ordering System**: Customers can place orders through the web interface
- **Order Management**: Track order status from pending to delivered
- **Real-time Updates**: Status changes reflect immediately across all interfaces
- **Reporting**: Generate sales reports and analytics
- **Receipt Printing**: Print physical or digital receipts

## Responsive Design

The system features a fully responsive design that adapts to various screen sizes:

- **Mobile Phones**: Optimized interface for small screens with touch-friendly buttons
- **Tablets**: Fluid layout that maximizes screen real estate for point-of-sale operations
- **Desktop**: Full-featured interface with advanced management capabilities
- **Adaptive Tables**: Data tables adjust columns based on available space
- **Flexible Images**: Product images scale appropriately for different devices

## Installation

1. Clone the repository to your local XAMPP `htdocs` directory:

```
git clone https://github.com/yourusername/bohemian-burrows.git
```

2. Import the database:
   - Make sure MySQL is running on port 3307
   - The system will automatically create a database named `bohemian`
   - You can verify by accessing the database setup page:
     http://localhost/bohemianburrows/includes/db_setup.php

3. Access the application:
   http://localhost/bohemianburrows/

## Accessing the System

- Main application: http://localhost/bohemianburrows
- Database setup:   
- Test connection: http://localhost/bohemianburrows/test_connection.php (if you created this file)

## Default Login Credentials

- **Admin**:
  - Username: admin
  - Password: admin123

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

## Directory Structure

```
bohemianburrows/
├── admin/           # Admin panel files
├── ajax/            # AJAX endpoints
├── assets/          # CSS, JavaScript, and images
├── cashier/         # Cashier panel files
├── includes/        # Helper files and database connection
├── uploads/         # Product images and other uploads
├── index.php        # Entry point
└── README.md        # This file
```

## Usage

1. Log in as admin to set up products and cashier accounts
2. Add products with prices, categories, and images
3. Create cashier accounts for store staff
4. Access the POS system to process sales
5. Generate reports for business analysis

## Setup Notes
- Ensure you have a placeholder image located at `assets/images/product-placeholder.png`. This image is used as a default if a product image is missing. A common dimension is 100x100 pixels.

## License

This project is licensed under the MIT License - see the LICENSE file for details.
