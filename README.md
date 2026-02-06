# Party Carpool Coordinator

## Application Overview
A complete web application for coordinating carpooling for party events. Users can register as drivers or riders, and the system helps match them together.

## Access Information

- **URL**: https://partycarpool.clodhost.com
- **SSL Certificate**: Self-signed (browser will show warning - proceed anyway)

## Database Credentials

- **Database Name**: partycarpool
- **Database User**: carpooluser
- **Database Password**: CarpoolPass2024!

## Features Implemented

1. **User Registration System**
   - Drivers can register with car details and available seats
   - Riders can register and request rides
   - Address geocoding with map pinpoints

2. **Interactive Map**
   - Shows event location with gold star
   - Green car icons for drivers
   - Blue person icons for riders
   - Click markers to see contact details

3. **Carpool Matching**
   - Match riders with drivers
   - Track pickup locations and times
   - Status management (pending/confirmed/cancelled)

4. **Real-time Statistics**
   - Available seats counter
   - Driver/Rider distribution chart
   - Confirmed matches tracking

## API Endpoints

- `GET /api/events.php?id=1` - Get event details
- `GET /api/users.php?event_id=1` - List all users
- `POST /api/users.php` - Register new user
- `GET /api/carpools.php?event_id=1` - List carpools
- `POST /api/carpools.php` - Create carpool match
- `PUT /api/carpools.php?id=X` - Update carpool status

## Sample Data

The system includes:
- 1 Event: "Summer Party 2024" scheduled for next week
- 1 Sample Driver: John Driver with a Toyota Camry (3 seats)
- 1 Sample Rider: Jane Rider
- 1 Confirmed carpool match between them

## Technologies Used

- **Backend**: PHP 8.3 with PDO for database access
- **Database**: MySQL 8.0
- **Web Server**: Apache 2.4 with SSL
- **Frontend**: HTML5, Bootstrap 5, JavaScript
- **Maps**: Leaflet.js with OpenStreetMap
- **Charts**: Chart.js for statistics

## Directory Structure

```
/var/www/partycarpool.clodhost.com/public/
├── index.html          # Main application page
├── api/               # Backend API endpoints
│   ├── users.php
│   ├── events.php
│   └── carpools.php
├── config/            # Database configuration
│   └── database.php
├── css/               # Stylesheets
│   └── style.css
└── js/                # JavaScript files
    └── app.js
```

## System Requirements Met

- Apache configured with SSL (port 443)
- Cloudflare "Full" SSL mode compatible
- Firewall configured for HTTPS
- Real MySQL database (not dummy data)
- Complete implementation (no placeholders)
- Interactive map with address pinpoints
- Contact details management
- Single-event focused design

## Testing the Application

1. Navigate to https://partycarpool.clodhost.com
2. Accept the self-signed certificate warning
3. Register as a driver or rider
4. View the interactive map
5. Create carpool matches
6. Monitor statistics on the dashboard

The application refreshes data every 30 seconds automatically.