import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '10s', target: 10 }, // Ramp up to 10 users
    { duration: '20s', target: 10 }, // Stay at 10 users
    { duration: '10s', target: 0 },  // Ramp down to 0 users
  ],
  thresholds: {
    http_req_duration: ['p(95)<2000'], // 95% of requests should be below 2000ms
    http_req_failed: ['rate<0.05'],    // Error rate should be less than 5%
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
    redirects: 0, // Don't follow redirects automatically
  });

  check(loginRes, {
    'login successful': (r) => r.status === 302 || r.status === 200,
    'redirected after login': (r) => r.status === 302,
  });

  sleep(1);
}
