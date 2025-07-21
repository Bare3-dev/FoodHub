# CORS Configuration Guide

## 📋 Overview

This Laravel application uses a comprehensive CORS (Cross-Origin Resource Sharing) configuration that automatically adapts to your development and production environments.

## 🌍 Environment-Based Configuration

### Development Environment (`APP_ENV=local`)
**Automatically allows these common development origins:**
- `http://localhost:3000` (React, Next.js default)
- `http://localhost:5173` (Vite default)
- `http://localhost:4200` (Angular default)
- `http://localhost:8080` (Vue CLI default)
- `http://localhost:8100` (Ionic default)
- `http://localhost:3001` (Alternative React port)
- `http://localhost:8000` (Alternative development port)
- IPv4 variants (`127.0.0.1` addresses)

**Development patterns also supported:**
- Any `localhost` port: `/^http:\/\/localhost:\d+$/`
- Ngrok tunnels: `*.ngrok.io`, `*.ngrok-free.app`
- Vercel previews: `*.vercel.app`
- Netlify previews: `*.netlify.app`

### Production Environment (`APP_ENV=production`)
**Only allows explicitly configured origins from environment variables:**
- `FRONTEND_URL` - Your main frontend domain
- `ADMIN_URL` - Your admin dashboard domain  
- `CORS_ALLOWED_ORIGINS` - Comma-separated additional origins

## ⚙️ Environment Variables Setup

Add these to your `.env` file:

```env
# CORS Configuration
FRONTEND_URL=https://yourapp.com
ADMIN_URL=https://admin.yourapp.com
CORS_ALLOWED_ORIGINS=https://app.yourapp.com,https://mobile.yourapp.com
CORS_MAX_AGE=86400
```

## 🔒 API Security Levels

### 1. Public Endpoints (`api.cors:public`)
**Access:** No authentication required  
**CORS:** Permissive (allows all origins)  
**Methods:** GET, HEAD, OPTIONS only  
**Headers:** Basic headers (no Authorization)

**Endpoints:**
- `GET /api/restaurants` - Browse restaurants
- `GET /api/menu-categories` - View menu categories
- `GET /api/menu-items` - View menu items
- `GET /api/restaurant-branches` - View restaurant branches

### 2. Private Endpoints (`api.cors:private`)
**Access:** Authentication required (`auth:sanctum`)  
**CORS:** Standard (configured origins only)  
**Methods:** All HTTP methods  
**Headers:** Authorization headers allowed

**Endpoints:**
- `GET /api/user` - User information
- `POST /api/orders` - Create orders
- `GET /api/orders` - View user's orders
- `POST /api/auth/logout` - Logout

### 3. Admin Endpoints (`api.cors:admin`)
**Access:** Admin roles required  
**CORS:** Strict (admin origins only)  
**Methods:** All HTTP methods  
**Headers:** Full authorization headers

**Endpoints:**
- `POST /api/restaurants` - Create restaurants
- `DELETE /api/restaurants/{id}` - Delete restaurants
- `POST /api/staff` - Create staff
- `POST /api/rate-limit/clear` - Clear rate limits

## 📨 Request Headers

### Required Headers for API Calls
```javascript
// For public endpoints
{
  'Accept': 'application/json',
  'Content-Type': 'application/json'
}

// For private/admin endpoints (add authentication)
{
  'Accept': 'application/json',
  'Content-Type': 'application/json',
  'Authorization': 'Bearer ' + token,  // Sanctum token
  'X-Requested-With': 'XMLHttpRequest'
}

// For SPA with CSRF protection
{
  'Accept': 'application/json',
  'Content-Type': 'application/json',
  'X-CSRF-TOKEN': csrfToken,
  'X-Requested-With': 'XMLHttpRequest'
}
```

## 📤 Response Headers

### API responses include these CORS headers:
- `Access-Control-Allow-Origin` - Your specific origin
- `Access-Control-Allow-Credentials` - `true` for authenticated endpoints
- `Access-Control-Expose-Headers` - Pagination and rate limit info

### Exposed headers you can read:
- `X-Pagination-Total` - Total records
- `X-Pagination-Per-Page` - Records per page
- `X-Pagination-Current-Page` - Current page
- `X-Pagination-Last-Page` - Last page number
- `X-Rate-Limit-Limit` - Rate limit maximum
- `X-Rate-Limit-Remaining` - Remaining requests
- `X-Rate-Limit-Reset` - Reset time
- `Retry-After` - Retry delay when rate limited

## 💻 Frontend Integration Examples

### Axios Configuration
```javascript
// axios-config.js
import axios from 'axios';

const api = axios.create({
  baseURL: process.env.NODE_ENV === 'production' 
    ? 'https://api.yourapp.com' 
    : 'http://localhost:8000',
  withCredentials: true, // Important for Sanctum
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  }
});

// Add auth token to requests
api.interceptors.request.use(config => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default api;
```

### Fetch API Configuration
```javascript
// api-client.js
const API_BASE = process.env.NODE_ENV === 'production' 
  ? 'https://api.yourapp.com' 
  : 'http://localhost:8000';

async function apiCall(endpoint, options = {}) {
  const token = localStorage.getItem('auth_token');
  
  const config = {
    credentials: 'include', // Important for Sanctum
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(token && { 'Authorization': `Bearer ${token}` }),
      ...options.headers
    },
    ...options
  };

  const response = await fetch(`${API_BASE}${endpoint}`, config);
  
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
  }
  
  return response.json();
}

export { apiCall };
```

### React Hook Example
```javascript
// useApi.js
import { useState, useEffect } from 'react';
import api from './axios-config';

export function useApiData(endpoint) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    api.get(endpoint)
      .then(response => {
        setData(response.data);
        setLoading(false);
      })
      .catch(err => {
        setError(err.message);
        setLoading(false);
      });
  }, [endpoint]);

  return { data, loading, error };
}

// Usage in component
function RestaurantList() {
  const { data: restaurants, loading, error } = useApiData('/api/restaurants');

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;
  
  return (
    <div>
      {restaurants.map(restaurant => (
        <div key={restaurant.id}>{restaurant.name}</div>
      ))}
    </div>
  );
}
```

## 🛠️ Testing CORS Configuration

### 1. Test Public Endpoints
```bash
curl -H "Origin: http://localhost:3000" \
     -H "Access-Control-Request-Method: GET" \
     -H "Access-Control-Request-Headers: accept,content-type" \
     -X OPTIONS \
     http://localhost:8000/api/restaurants
```

### 2. Test Private Endpoints
```bash
curl -H "Origin: http://localhost:3000" \
     -H "Access-Control-Request-Method: POST" \
     -H "Access-Control-Request-Headers: authorization,accept,content-type" \
     -X OPTIONS \
     http://localhost:8000/api/orders
```

### 3. Check Response Headers
```bash
curl -I -H "Origin: http://localhost:3000" \
     http://localhost:8000/api/restaurants
```

## ⚠️ Common Issues & Solutions

### Issue: CORS errors in development
**Solution:** Ensure your frontend runs on a supported localhost port (3000, 5173, 4200, 8080, 8100)

### Issue: Authentication not working
**Solution:** Make sure `withCredentials: true` is set in your HTTP client

### Issue: Custom headers blocked
**Solution:** Add your custom headers to `allowed_headers` in `config/cors.php`

### Issue: Production CORS errors
**Solution:** Verify `FRONTEND_URL` and `ADMIN_URL` are correctly set in production `.env`

## 📱 Mobile App Considerations

For mobile apps (React Native, Flutter, etc.):
- Add your development server IPs to `CORS_ALLOWED_ORIGINS`
- Consider using domain-based origins for production mobile apps
- Test with your device's local IP address during development

## 🔧 Customization

To modify CORS settings:
1. Edit `config/cors.php` for global settings
2. Modify `app/Http/Middleware/ApiCorsMiddleware.php` for endpoint-specific rules
3. Update route groups in `routes/api.php` to use different CORS types

## 📋 Production Checklist

- [ ] Set `APP_ENV=production` in production
- [ ] Configure `FRONTEND_URL` with your production domain
- [ ] Configure `ADMIN_URL` if you have an admin dashboard
- [ ] Add any additional origins to `CORS_ALLOWED_ORIGINS`
- [ ] Test all frontend integrations with production API
- [ ] Verify authentication flows work with CORS
- [ ] Test preflight OPTIONS requests 