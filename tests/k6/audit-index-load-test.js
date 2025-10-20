import http from 'k6/http';
import {check, sleep} from 'k6';

export const options = {
    stages: [
        {duration: '10s', target: 10}, // Ramp up to 10 users
        {duration: '20s', target: 10}, // Stay at 10 users
        {duration: '10s', target: 0},  // Ramp down to 0 users
    ],
    thresholds: {
        'http_req_duration{name:audit_index}': ['p(95)<3500'], // Only measure the audit index request
        'http_req_failed{name:audit_index}': ['rate<0.05'],
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

const users = [
    {email: 'user_sac1@example.com', password: 'password'},
    {email: 'user_sac2@example.com', password: 'password'},
    {email: 'user_sac3@example.com', password: 'password'},
    {email: 'user_sac4@example.com', password: 'password'},
    {email: 'user_sac5@example.com', password: 'password'},
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
        'login successful': (r) => r.status === 302 || r.status === 200,
    });

    // Step 3: Access audit index page
    const auditRes = http.get(`${BASE_URL}/audit`, {
        tags: { name: 'audit_index' },
    });

    check(auditRes, {
        'audit page loaded': (r) => r.status === 200,
        'audit page has content': (r) => r.body.length > 0,
        'audit page has title and filters': (r) => r.body.includes('Audit') && r.body.includes('Select Tenant') && r.body.includes('Filters'),
    });

    sleep(1);
}
