import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '10s', target: 10 }, // Ramp up to 10 users
    { duration: '20s', target: 10 }, // Stay at 10 users
    { duration: '10s', target: 0 },  // Ramp down to 0 users
  ],
  thresholds: {
    'http_req_duration{name:audit_filter}': ['p(95)<3500'], // Only measure the filtered audit request
    'http_req_failed{name:audit_filter}': ['rate<0.05'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

const users = [
  { email: 'user_sac1@example.com', password: 'password' },
  { email: 'user_sac2@example.com', password: 'password' },
  { email: 'user_sac3@example.com', password: 'password' },
  { email: 'user_sac4@example.com', password: 'password' },
  { email: 'user_sac5@example.com', password: 'password' },
];

// Entity ID for "Usuarios" (Users entity)
const USERS_ENTITY_ID = '00000000-0000-0000-0000-000000000002';

export default function () {
  // Select a random user from the list
  const user = users[Math.floor(Math.random() * users.length)];

  // Use a cookie jar to maintain session
  const jar = http.cookieJar();
  jar.set(BASE_URL, 'k6_session', 'active');

  // Step 1: Get login page to retrieve CSRF token
  const loginPageRes = http.get(`${BASE_URL}/login`);

  check(loginPageRes, {
    'login page loaded': (r) => r.status === 200,
  });

  // Extract CSRF token from the response
  const csrfToken = loginPageRes.html().find('input[name="_token"]').attr('value');

  // Step 2: Submit login form
  const loginRes = http.post(`${BASE_URL}/login`, {
    email: user.email,
    password: user.password,
    _token: csrfToken,
  }, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });

  check(loginRes, {
    'login successful': (r) => r.status === 200 || r.status === 302,
  });

  // Step 3: Select first tenant (simulate tenant selection)
  const auditInitialRes = http.get(`${BASE_URL}/audit`);

  check(auditInitialRes, {
    'initial audit page loaded': (r) => r.status === 200,
  });

  // Extract CSRF token for tenant update
  const updateCsrfToken = auditInitialRes.html().find('input[name="_token"]').attr('value');

  // Get first tenant ID from the page (simulate selecting first tenant from dropdown)
  const tenantSelectMatch = auditInitialRes.body.match(/value="([0-9a-f-]+)"/);
  const firstTenantId = tenantSelectMatch ? tenantSelectMatch[1] : null;

  if (firstTenantId && updateCsrfToken) {
    // Step 4: Update tenant selection
    const updateTenantRes = http.post(`${BASE_URL}/update-tenant`, {
      tenant_id: firstTenantId,
      _token: updateCsrfToken,
    }, {
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
    });

    check(updateTenantRes, {
      'tenant updated': (r) => r.status === 200 || r.status === 302,
    });
  }

  // Step 5: Access audit page with Users entity filter
  const auditFilterRes = http.get(`${BASE_URL}/audit?entity_id=${USERS_ENTITY_ID}`, {
    tags: { name: 'audit_filter' },
  });

  check(auditFilterRes, {
    'audit page with users filter loaded': (r) => r.status === 200,
    'audit page has content': (r) => r.body.length > 0,
    'has multiple audit records': (r) => (r.body.match(/audit-record/g) || []).length > 1,
  });

  sleep(1);
}
