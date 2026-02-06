# Party Carpool Test Workflow Guide

## 🎉 Test Scenario: Madison Winter Celebration 2024

You now have a realistic test environment with 24 participants spread across Madison, WI for a party on State Street in 3 days at 7 PM.

### Test Data Summary:
- **Total Participants**: 24 people
- **Willing to Drive**: 10 drivers (53 total seats available)
- **Need Rides**: 14 riders
- **Party Location**: 615 State Street, Madison, WI 53703
- **Date/Time**: 3 days from today at 7:00 PM

### Geographic Distribution:
- **West Side** (University/Hilldale): 3 people (2 drivers)
- **East Side** (Williamson/Atwood): 4 people (2 drivers)
- **North Side** (Sherman Ave): 4 people (2 drivers)
- **South Side** (Park St): 4 people (1 driver)
- **Downtown/Campus**: 3 people (1 driver)
- **Far West** (Middleton border): 3 people (1 driver with 8-seat SUV!)
- **Fitchburg**: 3 people (1 driver)

## 📋 Complete Testing Workflow

### Step 1: Public View (User Perspective)
1. **Open the main app**: https://partycarpool.clodhost.com/
2. **Check the Home tab**:
   - ✅ Map should show gold star at State Street party location
   - ✅ Green car icons for 10 willing drivers
   - ✅ Blue user icons for 14 riders
   - ✅ Header shows "Madison Winter Celebration 2024"
   - ✅ Shows "24 Total | 10 Can Drive"
   - ✅ Map zooms to show all participants

3. **Test the Register tab**:
   - Try registering a new participant
   - Test both "willing to drive" and "need a ride" options
   - Notice vehicle capacity field appears only for drivers
   - Use "Locate on Map" to geocode your address

### Step 2: Admin Dashboard
1. **Login to Admin**: https://partycarpool.clodhost.com/admin/
   - Username: `admin`
   - Password: `Admin2024!`

2. **Review Event Details**:
   - Event shows party location on State Street
   - 24 registered participants displayed
   - Optimization status: "Pending"

3. **Check Participants Tab**:
   - Review the list of all 24 test participants
   - Note the variety of vehicle capacities (4-8 seats)
   - See geographic spread across Madison

### Step 3: Run Optimization Algorithm

1. **Click "Optimization" Tab** in admin dashboard
2. **Click "Run Optimization Algorithm"**
3. **Review Results**:
   - Algorithm will use ~5-7 vehicles (instead of all 10 drivers)
   - Clusters participants by neighborhood
   - Assigns largest vehicles first
   - Shows departure times for each driver
   - Shows pickup times for each passenger

### Step 4: View Assignments
1. **Check the optimization results**:
   - Each driver gets their departure time (e.g., "6:15 PM")
   - Passengers see pickup times (e.g., "6:25 PM")
   - Routes are optimized by geographic proximity
   - Total distance minimized

2. **Notable optimizations to look for**:
   - Patricia White (8-seat SUV) from Far West picks up multiple riders
   - Downtown participants (Kevin, Sophia, Daniel) grouped together
   - East side cluster (Tom, Lisa, James, Amanda) carpools together
   - North side residents share rides efficiently

### Step 5: Test Edge Cases

1. **Add More Participants**:
   ```sql
   -- Add someone very far away
   INSERT INTO users (name, email, willing_to_drive, address, lat, lng, event_id)
   VALUES ('Test User', 'test@example.com', FALSE, 'Sun Prairie, WI', 43.1836, -89.2135, 1);
   ```

2. **Test with Different Scenarios**:
   - Add more riders than capacity (should handle overflow)
   - Add driver with 2-seat sports car
   - Test with participants without geocoded addresses

### Step 6: Communication Testing

After optimization, the system provides:
- **For Drivers**: "Depart at 6:15 PM to pick up passengers"
- **For Passengers**: "Your driver will arrive at 6:25 PM"

## 🔍 What to Verify

### Map Features:
- [x] Party location star is pulsing gold
- [x] Driver markers are green cars with white backgrounds
- [x] Rider markers are blue users with white backgrounds
- [x] Hover effects scale markers up
- [x] Popups show participant details

### Optimization Results:
- [x] Minimizes total vehicles used
- [x] Groups by geographic proximity
- [x] Calculates realistic departure/pickup times
- [x] Handles various vehicle capacities
- [x] Assigns efficient routes

### Time Calculations:
- [x] Assumes 30 mph average city speed
- [x] Adds 3 minutes per pickup stop
- [x] Includes 10-minute early arrival buffer
- [x] Shows times in readable format (e.g., "6:15 PM")

## 💡 Testing Tips

1. **Reset Test Data**: Run `/tmp/create_test_scenario.sql` again to reset
2. **View Database**: Check assignments in `carpool_assignments` table
3. **Test Registration**: Try adding yourself as a participant
4. **Check Mobile**: Test responsive design on phone/tablet

## 🎯 Expected Optimization Results

With 24 participants and 10 willing drivers:
- **Optimal vehicles needed**: 5-7 cars
- **Vehicles saved**: 3-5 cars
- **Average passengers per car**: 3-4 people
- **Longest route**: Far West/Fitchburg drivers (~8-10 miles)
- **Shortest route**: Downtown drivers (~1-2 miles)

## 📊 SQL Queries for Verification

```sql
-- Check current participant counts
SELECT willing_to_drive, COUNT(*) as count,
       SUM(vehicle_capacity) as total_seats
FROM users WHERE event_id = 1
GROUP BY willing_to_drive;

-- View optimization results
SELECT ca.*, COUNT(cp.id) as passenger_count
FROM carpool_assignments ca
LEFT JOIN carpool_passengers cp ON ca.id = cp.assignment_id
WHERE ca.is_active = TRUE
GROUP BY ca.id;

-- Check pickup times
SELECT u.name, cp.pickup_time, ca.departure_time
FROM carpool_passengers cp
JOIN users u ON cp.passenger_user_id = u.id
JOIN carpool_assignments ca ON cp.assignment_id = ca.id
ORDER BY ca.id, cp.pickup_order;
```

## 🚀 Start Testing!

Your test environment is ready. Visit https://partycarpool.clodhost.com/ to begin testing the complete workflow!