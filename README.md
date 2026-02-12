# PartyPool - Party Carpool Coordinator

## Application Overview
A complete web application for coordinating carpooling for party events. Users can register as drivers or riders, and the system helps match them together with intelligent optimization.

## Features Implemented

### Core Features
1. **User Registration System**
   - Drivers can register with car details and available seats
   - Riders can register and request rides
   - Address geocoding with map pinpoints

2. **Interactive Map**
   - Shows event location with gold star marker
   - Green car icons for drivers
   - Blue person icons for riders
   - Click markers to see contact details

3. **Admin Dashboard**
   - Event management and editing
   - Carpool optimization with K-means clustering
   - Overhead time calculations (direct vs carpool routes)
   - Adjustable vehicle count for optimal distribution
   - Real-time form validation

4. **Carpool Optimization**
   - Automatic vehicle minimization
   - Driver route planning with time estimates
   - Overhead display showing extra driving time
   - Visual warnings for high overhead (>20 minutes)
   - Rerun capability with different vehicle counts

## Recent Enhancements

- Map styling unified across homepage and admin dashboard
- Overhead time calculations for driver route planning
- Vehicle count adjustment after initial optimization
- Visual warnings for high driving overhead
- Improved UI flow: run optimization first, then adjust

## API Endpoints

- `GET /api/events.php?id=1` - Get event details
- `GET /api/users.php?event_id=1` - List all users
- `POST /api/users.php` - Register new user
- `GET /api/carpools.php?event_id=1` - List carpools
- `POST /api/carpools.php` - Create carpool match
- `PUT /api/carpools.php?id=X` - Update carpool status

## Technologies Used

- **Backend**: PHP 8.3 with PDO for database access
- **Database**: MySQL 8.0
- **Web Server**: Apache 2.4 with SSL
- **Frontend**: HTML5, Bootstrap 5, JavaScript
- **Maps**: Leaflet.js with OpenStreetMap
- **Geocoding**: OpenStreetMap Nominatim API
- **Optimization**: K-means clustering algorithm

## Setup Instructions

1. Clone the repository
2. Copy `public/config/database.php.example` to `public/config/database.php`
3. Update database credentials in the config file
4. Import database schema
5. Configure web server to point to public directory
6. Navigate to the application URL

## Directory Structure

```
/public/
├── index.html          # Main application page
├── admin/              # Admin dashboard
│   ├── dashboard.php
│   ├── login.php
│   └── optimize_enhanced.php
├── api/                # Backend API endpoints
│   ├── users.php
│   ├── events.php
│   └── carpools.php
├── config/             # Database configuration
│   └── database.php
├── css/                # Stylesheets
│   └── style.css
└── js/                 # JavaScript files
    └── app.js
└── registration/       # Registration page
    └── registration_dashboard.html
    └── registration.php

```


## License

This project is open source and available for educational purposes.
