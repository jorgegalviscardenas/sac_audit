import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '10s', target: 10 }, // Ramp up to 10 users
    { duration: '20s', target: 10 }, // Stay at 10 users
    { duration: '10s', target: 0 },  // Ramp down to 0 users
  ],
  thresholds: {
    'http_req_duration{name:tenant_switch}': ['p(95)<2000'], // Only measure the tenant switch request
    'http_req_failed{name:tenant_switch}': ['rate<0.05'],
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

  // Step 3: Access audit page to get tenant list
  const auditRes = http.get(`${BASE_URL}/audit`);

  check(auditRes, {
    'audit page loaded': (r) => r.status === 200,
  });

  // Extract CSRF token for tenant update
  const updateCsrfToken = auditRes.html().find('input[name="_token"]').attr('value');

  // Extract all tenant IDs from the page
  const tenantMatches = auditRes.body.matchAll(/<option value="([0-9a-f-]+)"/g);
  const tenantIds = Array.from(tenantMatches, match => match[1]);

  if (tenantIds.length > 0 && updateCsrfToken) {
    // Select a random tenant
    const selectedTenantId = tenantIds[Math.floor(Math.random() * tenantIds.length)];

    // Step 4: Update tenant selection (this is the request we're measuring)
    const updateTenantRes = http.post(`${BASE_URL}/update-tenant`, {
      tenant_id: selectedTenantId,
      _token: updateCsrfToken,
    }, {
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      tags: { name: 'tenant_switch' },
    });

    check(updateTenantRes, {
      'tenant switch successful': (r) => r.status === 200 || r.status === 302,
      'tenant switch response has content': (r) => r.body.length > 0,
    });

    // Step 5: Verify the tenant was switched by accessing audit page again
    const verifyRes = http.get(`${BASE_URL}/audit`);

    check(verifyRes, {
      'audit page reloaded after switch': (r) => r.status === 200,
      'tenant is selected': (r) => r.body.includes(selectedTenantId) || r.body.includes('selected'),
    });
  }

  sleep(1);
}
