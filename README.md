# Micron Production Tracking - Pilot System

## Live Component & Materials Tracking System

A real-time production tracking web application demonstrating live tracking of components and materials as they move through manufacturing stages and bins. Built with React frontend, PHP backend, and MySQL database.

## Features

✅ **Live Stage Tracking** - Track parts through all manufacturing stages  
✅ **Bin Management** - Monitor inventory across multiple bins  
✅ **Manual Input** - Operators can input quantities manually  
✅ **Auto Calculations** - Automatic rework and rejection calculations  
✅ **Real-time Dashboard** - Live updates every 5 seconds  
✅ **Multi-Part Support** - Track multiple part numbers simultaneously  
✅ **Movement History** - Track all bin-to-bin transfers  

## Technology Stack

- **Frontend**: React 18 (via CDN)
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Hosting**: Shared hosting compatible

## Installation

### 1. Database Setup

```bash
# Import the database schema
mysql -u your_username -p < database.sql
```

### 2. Configure Database Connection

Edit `api/config.php` and update your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'micron_tracking');
```

### 3. Deploy to Shared Hosting

1. Upload all files to your shared hosting (e.g., via FTP/cPanel)
2. Place `public/index.html` in your web root (e.g., `public_html`)
3. Place the `api/` folder in your web root
4. Ensure `.htaccess` allows PHP execution

### 4. Folder Structure on Server

```
public_html/
├── index.html          (from public/)
├── api/
│   ├── config.php
│   ├── index.php
│   ├── parts.php
│   ├── inventory.php
│   └── dashboard.php
```

### 5. Update API Base URL

In `public/index.html`, update the API base URL:

```javascript
const API_BASE = '/api';  // Or your full URL: 'https://yourdomain.com/api'
```

## Usage

### Accessing the Dashboard

Navigate to: `https://yourdomain.com/` or `https://yourdomain.com/index.html`

### Manual Input Workflow

1. **Select a part** from the sidebar
2. **Choose a stage** from the dropdown
3. **Select a bin** where materials are located
4. **Input quantities**:
   - Total Quantity (required)
   - Good Quantity
   - Rework Quantity
   - Rejected Quantity
5. Click **Update Inventory**

### Auto Calculations

The system automatically:
- Calculates rework items and routes them to the rework bin
- Tracks rejected quantities separately
- Updates stage-wise inventory in real-time
- Records all movements for audit trails

### Viewing Live Data

- Dashboard refreshes automatically every 5 seconds
- View stage-wise breakdown for each part
- See active bins and their quantities
- Monitor good/rework/rejected counts at each stage

## Database Schema

### Tables

1. **parts** - Part master data (part numbers, categories)
2. **stages** - Manufacturing stages for each part
3. **bins** - Storage bins across the facility
4. **inventory** - Current stock at each stage/bin
5. **movements** - Historical bin-to-bin transfers

## Parts Included (from CSV)

**200 Series Agriculture Parts:**
- RW 236 A (13 stages)
- RW 236 B (11 stages)
- RW 237 (11 stages)
- RW 238 (11 stages)
- RW 239 (11 stages)
- RW 211 (12 stages)
- RW 212 A (13 stages)
- *Additional parts can be added via database*

## Sample Manufacturing Stages

- Incoming (IN)
- CNC Machining (CNC-1, CNC-2)
- Body Drill (Rough)
- Ear Drill / Ear Bore
- Broaching
- Pin Drill / Face Drill
- Chamfering
- Heat Treatment (HT-1, HT-2)
- Quality Check (QC)
- Finished Goods (FG)

## API Endpoints

### GET `/api/dashboard.php`
Get complete dashboard overview with parts, stages, inventory, and bins

### GET `/api/parts.php`
List all parts with their stages

### POST `/api/parts.php`
Add new part with stages

### GET `/api/inventory.php?part_id=1`
Get inventory for specific part

### POST `/api/inventory.php`
Update inventory (manual input or auto transfer)

**Actions:**
- `update` - Manual inventory input
- `transfer` - Auto bin-to-bin transfer
- `rework` - Handle rework items

## Proof of Concept Benefits

### For Micron Enterprises:

1. **Real-time Visibility** - See exactly where every part is at any moment
2. **Quality Tracking** - Monitor good/rework/rejected quantities per stage
3. **Bin Optimization** - Track bin utilization and capacity
4. **Audit Trail** - Complete movement history for compliance
5. **Data-Driven Decisions** - Analytics on bottlenecks and efficiency
6. **Scalability** - Easy to add more parts, stages, and bins

## Support

For questions or issues:
- Email: support@zeroaitech.com
- GitHub Issues: [Create an issue](https://github.com/Lottie128/micron-pilot/issues)

## License

Proprietary - Micron Enterprises / ZeroAI Technologies

---

**Built by ZeroAI Technologies** 🚀
