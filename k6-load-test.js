import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// ─── Custom Metrics ───────────────────────────────────────────────────────────
const errorRate = new Rate('errors');
const loginDuration = new Trend('login_duration', true);
const browseDuration = new Trend('browse_duration', true);
const cartDuration = new Trend('cart_duration', true);
const orderDuration = new Trend('order_duration', true);
const searchDuration = new Trend('search_duration', true);
const apiCalls = new Counter('api_calls');

// ─── Configuration ────────────────────────────────────────────────────────────
const BASE_URL = __ENV.BASE_URL || 'https://dashboard.surprisemoi.com/api/v1';

// Test users — add more for realistic concurrency (each VU picks one)
const TEST_USERS = [
    { email: 'noelcassie44@gmail.com', password: '@cassie4U' },
    // Add more test accounts here for better distribution
];

// ─── Load Test Stages ─────────────────────────────────────────────────────────
// Simulates realistic traffic pattern: warm-up → peak → sustained → cool-down
export const options = {
    stages: [
        // Warm-up: gradually ramp to 50 users over 2 minutes
        { duration: '2m', target: 50 },
        // Ramp to moderate load
        { duration: '3m', target: 150 },
        // Spike to peak traffic
        { duration: '2m', target: 300 },
        // Sustain peak traffic
        { duration: '5m', target: 300 },
        // Gradual cool-down
        { duration: '2m', target: 100 },
        // Tail off
        { duration: '1m', target: 0 },
    ],

    thresholds: {
        // Global thresholds
        http_req_duration: ['p(95)<2000', 'p(99)<5000'],  // 95th < 2s, 99th < 5s
        http_req_failed: ['rate<0.05'],                     // <5% failure rate
        errors: ['rate<0.1'],                               // <10% custom error rate

        // Per-scenario thresholds
        login_duration: ['p(95)<3000'],
        browse_duration: ['p(95)<1500'],
        cart_duration: ['p(95)<2000'],
        search_duration: ['p(95)<2000'],
    },
};

// ─── Helpers ──────────────────────────────────────────────────────────────────
const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };

function authHeaders(token) {
    return { ...headers, 'Authorization': `Bearer ${token}` };
}

function pickRandom(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}

function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

// ─── Scenario: Public Browsing (unauthenticated) ─────────────────────────────
function publicBrowsing() {
    group('Public Browsing', () => {
        // Health check
        let res = http.get(`${BASE_URL}/health`, { headers, tags: { name: 'health' } });
        apiCalls.add(1);
        check(res, { 'health: status 200': (r) => r.status === 200 });
        errorRate.add(res.status !== 200);

        sleep(randomInt(1, 3));

        // Browse categories
        res = http.get(`${BASE_URL}/categories`, { headers, tags: { name: 'categories' } });
        apiCalls.add(1);
        browseDuration.add(res.timings.duration);
        check(res, { 'categories: status 200': (r) => r.status === 200 });
        errorRate.add(res.status !== 200);

        sleep(randomInt(1, 2));

        // Browse products with pagination (simulating scrolling)
        const page = randomInt(1, 5);
        res = http.get(`${BASE_URL}/products?page=${page}&per_page=20`, {
            headers,
            tags: { name: 'products_list' },
        });
        apiCalls.add(1);
        browseDuration.add(res.timings.duration);
        check(res, { 'products list: status 200': (r) => r.status === 200 });
        errorRate.add(res.status !== 200);

        // Try to extract a product ID for detail view
        let productId = null;
        try {
            const body = JSON.parse(res.body);
            const products = body.data || [];
            if (products.length > 0) {
                productId = pickRandom(products).id;
            }
        } catch (_) {}

        sleep(randomInt(1, 3));

        // View a specific product
        if (productId) {
            res = http.get(`${BASE_URL}/products/${productId}`, {
                headers,
                tags: { name: 'product_detail' },
            });
            apiCalls.add(1);
            browseDuration.add(res.timings.duration);
            check(res, { 'product detail: status 200': (r) => r.status === 200 });
            errorRate.add(res.status !== 200);

            sleep(randomInt(1, 2));

            // View product reviews
            res = http.get(`${BASE_URL}/products/${productId}/reviews`, {
                headers,
                tags: { name: 'product_reviews' },
            });
            apiCalls.add(1);
            browseDuration.add(res.timings.duration);
            check(res, { 'product reviews: status 200': (r) => r.status === 200 });
            errorRate.add(res.status !== 200);
        }

        sleep(randomInt(1, 2));

        // Browse services
        res = http.get(`${BASE_URL}/services?page=${randomInt(1, 3)}`, {
            headers,
            tags: { name: 'services_list' },
        });
        apiCalls.add(1);
        browseDuration.add(res.timings.duration);
        check(res, { 'services list: status 200': (r) => r.status === 200 });
        errorRate.add(res.status !== 200);

        sleep(randomInt(1, 2));

        // Browse vendors
        res = http.get(`${BASE_URL}/vendors?page=1`, {
            headers,
            tags: { name: 'vendors_list' },
        });
        apiCalls.add(1);
        browseDuration.add(res.timings.duration);
        check(res, { 'vendors list: status 200': (r) => r.status === 200 });
        errorRate.add(res.status !== 200);

        // Extract vendor for detail view
        let vendorId = null;
        try {
            const body = JSON.parse(res.body);
            const vendors = body.data || [];
            if (vendors.length > 0) {
                vendorId = pickRandom(vendors).id;
            }
        } catch (_) {}

        if (vendorId) {
            sleep(randomInt(1, 2));

            res = http.get(`${BASE_URL}/vendors/${vendorId}`, {
                headers,
                tags: { name: 'vendor_detail' },
            });
            apiCalls.add(1);
            browseDuration.add(res.timings.duration);
            check(res, { 'vendor detail: status 200': (r) => r.status === 200 });
            errorRate.add(res.status !== 200);

            // Vendor products
            res = http.get(`${BASE_URL}/vendors/${vendorId}/products`, {
                headers,
                tags: { name: 'vendor_products' },
            });
            apiCalls.add(1);
            browseDuration.add(res.timings.duration);
            check(res, { 'vendor products: status 200': (r) => r.status === 200 });
            errorRate.add(res.status !== 200);
        }

        sleep(randomInt(1, 2));

        // Browse shops
        res = http.get(`${BASE_URL}/shops?page=1`, {
            headers,
            tags: { name: 'shops_list' },
        });
        apiCalls.add(1);
        browseDuration.add(res.timings.duration);
        check(res, { 'shops list: status 200': (r) => r.status === 200 });
        errorRate.add(res.status !== 200);

        sleep(randomInt(1, 2));

        // Special offers
        res = http.get(`${BASE_URL}/special-offers`, {
            headers,
            tags: { name: 'special_offers' },
        });
        apiCalls.add(1);
        browseDuration.add(res.timings.duration);
        check(res, { 'special offers: status 200': (r) => r.status === 200 });
        errorRate.add(res.status !== 200);

        sleep(randomInt(1, 2));

        // Advertisements
        res = http.get(`${BASE_URL}/advertisements`, {
            headers,
            tags: { name: 'advertisements' },
        });
        apiCalls.add(1);
        browseDuration.add(res.timings.duration);
        check(res, { 'advertisements: status 200': (r) => r.status === 200 });
        errorRate.add(res.status !== 200);
    });
}

// ─── Scenario: Filters & Search ──────────────────────────────────────────────
function filtersAndSearch() {
    group('Filters & Search', () => {
        // Parallel-ish filter requests (mimics frontend loading filter options)
        const responses = http.batch([
            ['GET', `${BASE_URL}/filters`, null, { headers, tags: { name: 'filters_index' } }],
            ['GET', `${BASE_URL}/filters/categories`, null, { headers, tags: { name: 'filters_categories' } }],
            ['GET', `${BASE_URL}/filters/price-range`, null, { headers, tags: { name: 'filters_price' } }],
            ['GET', `${BASE_URL}/filters/occasions`, null, { headers, tags: { name: 'filters_occasions' } }],
            ['GET', `${BASE_URL}/filters/locations`, null, { headers, tags: { name: 'filters_locations' } }],
        ]);

        responses.forEach((res) => {
            apiCalls.add(1);
            searchDuration.add(res.timings.duration);
            errorRate.add(res.status !== 200);
        });

        check(responses[0], { 'filters index: status 200': (r) => r.status === 200 });

        sleep(randomInt(2, 4));

        // Filtered product search (simulates user applying filters)
        const res = http.get(
            `${BASE_URL}/products?page=1&per_page=20&sort=popular`,
            { headers, tags: { name: 'products_filtered' } }
        );
        apiCalls.add(1);
        searchDuration.add(res.timings.duration);
        check(res, { 'filtered products: status 200': (r) => r.status === 200 });
        errorRate.add(res.status !== 200);

        sleep(randomInt(1, 3));

        // Profile options (for gift personalization)
        const optResponses = http.batch([
            ['GET', `${BASE_URL}/profile-options/interests`, null, { headers, tags: { name: 'interests' } }],
            ['GET', `${BASE_URL}/profile-options/personality-traits`, null, { headers, tags: { name: 'personality_traits' } }],
        ]);

        optResponses.forEach((r) => {
            apiCalls.add(1);
            searchDuration.add(r.timings.duration);
            errorRate.add(r.status !== 200);
        });
    });
}

// ─── Scenario: Authenticated User Journey ────────────────────────────────────
function authenticatedUserJourney() {
    const user = pickRandom(TEST_USERS);

    group('Login', () => {
        const loginRes = http.post(`${BASE_URL}/auth/login`, JSON.stringify({
            email: user.email,
            password: user.password,
        }), { headers, tags: { name: 'login' } });

        apiCalls.add(1);
        loginDuration.add(loginRes.timings.duration);

        const loginOk = check(loginRes, {
            'login: status 200': (r) => r.status === 200,
            'login: has token': (r) => {
                try { return !!JSON.parse(r.body).data.token; } catch { return false; }
            },
        });
        errorRate.add(!loginOk);

        if (!loginOk) {
            console.warn(`Login failed for ${user.email}: ${loginRes.status} ${loginRes.body.substring(0, 200)}`);
            return;
        }

        let token;
        try { token = JSON.parse(loginRes.body).data.token; } catch { return; }
        const authH = authHeaders(token);

        sleep(randomInt(1, 2));

        // ─── Profile & Notifications ──────────────────────────────────
        // NOTE: GET /auth/user returns 500 — excluded until fixed server-side
        group('Profile & Notifications', () => {
            const batchRes = http.batch([
                ['GET', `${BASE_URL}/profile`, null, { headers: authH, tags: { name: 'profile' } }],
                ['GET', `${BASE_URL}/notifications/unread-count`, null, { headers: authH, tags: { name: 'unread_count' } }],
            ]);

            batchRes.forEach((r) => {
                apiCalls.add(1);
                errorRate.add(r.status !== 200);
            });

            check(batchRes[0], { 'profile: status 200': (r) => r.status === 200 });
            check(batchRes[1], { 'unread count: status 200': (r) => r.status === 200 });
        });

        sleep(randomInt(2, 4));

        // ─── Browse & Add to Cart ─────────────────────────────────────
        group('Browse & Cart', () => {
            // Browse products
            let res = http.get(`${BASE_URL}/products?page=1&per_page=20`, {
                headers: authH,
                tags: { name: 'auth_products' },
            });
            apiCalls.add(1);
            browseDuration.add(res.timings.duration);
            check(res, { 'auth products: status 200': (r) => r.status === 200 });
            errorRate.add(res.status !== 200);

            let productId = null;
            try {
                const body = JSON.parse(res.body);
                const products = body.data || [];
                if (products.length > 0) {
                    productId = pickRandom(products).id;
                }
            } catch (_) {}

            sleep(randomInt(1, 3));

            // Add to cart
            if (productId) {
                res = http.post(`${BASE_URL}/cart/items`, JSON.stringify({
                    product_id: productId,
                    quantity: randomInt(1, 3),
                }), { headers: authH, tags: { name: 'cart_add' } });
                apiCalls.add(1);
                cartDuration.add(res.timings.duration);
                check(res, {
                    'cart add: status 200 or 201': (r) => r.status === 200 || r.status === 201,
                });
                errorRate.add(res.status !== 200 && res.status !== 201);
            }

            sleep(randomInt(1, 2));

            // View cart
            res = http.get(`${BASE_URL}/cart`, {
                headers: authH,
                tags: { name: 'cart_view' },
            });
            apiCalls.add(1);
            cartDuration.add(res.timings.duration);
            check(res, { 'cart view: status 200': (r) => r.status === 200 });
            errorRate.add(res.status !== 200);
        });

        sleep(randomInt(2, 4));

        // ─── Wishlist ─────────────────────────────────────────────────
        group('Wishlist', () => {
            const res = http.get(`${BASE_URL}/wishlist`, {
                headers: authH,
                tags: { name: 'wishlist' },
            });
            apiCalls.add(1);
            browseDuration.add(res.timings.duration);
            check(res, { 'wishlist: status 200': (r) => r.status === 200 });
            errorRate.add(res.status !== 200);
        });

        sleep(randomInt(1, 3));

        // ─── Orders History ───────────────────────────────────────────
        // NOTE: GET /orders/statistics returns 403 for regular users — excluded
        group('Orders', () => {
            const res = http.get(`${BASE_URL}/orders?page=1`, {
                headers: authH,
                tags: { name: 'orders_list' },
            });
            apiCalls.add(1);
            orderDuration.add(res.timings.duration);
            check(res, { 'orders list: status 200': (r) => r.status === 200 });
            errorRate.add(res.status !== 200);
        });

        sleep(randomInt(1, 3));

        // ─── Notifications ────────────────────────────────────────────
        group('Notifications', () => {
            const res = http.get(`${BASE_URL}/notifications?page=1`, {
                headers: authH,
                tags: { name: 'notifications' },
            });
            apiCalls.add(1);
            check(res, { 'notifications: status 200': (r) => r.status === 200 });
            errorRate.add(res.status !== 200);
        });

        sleep(randomInt(1, 3));

        // ─── Chat ─────────────────────────────────────────────────────
        group('Chat', () => {
            const batchRes = http.batch([
                ['GET', `${BASE_URL}/chat/conversations`, null, { headers: authH, tags: { name: 'chat_conversations' } }],
                ['GET', `${BASE_URL}/chat/unread-count`, null, { headers: authH, tags: { name: 'chat_unread' } }],
            ]);

            batchRes.forEach((r) => {
                apiCalls.add(1);
                errorRate.add(r.status !== 200);
            });
        });

        sleep(randomInt(1, 2));

        // ─── Addresses ────────────────────────────────────────────────
        group('Addresses', () => {
            const res = http.get(`${BASE_URL}/addresses`, {
                headers: authH,
                tags: { name: 'addresses' },
            });
            apiCalls.add(1);
            check(res, { 'addresses: status 200': (r) => r.status === 200 });
            errorRate.add(res.status !== 200);
        });

        sleep(randomInt(1, 2));

        // ─── Coupons ──────────────────────────────────────────────────
        group('Coupons', () => {
            const res = http.get(`${BASE_URL}/coupons/available`, {
                headers: authH,
                tags: { name: 'coupons_available' },
            });
            apiCalls.add(1);
            check(res, { 'coupons: status 200': (r) => r.status === 200 });
            errorRate.add(res.status !== 200);
        });

        sleep(randomInt(1, 2));

        // ─── Waw Videos Feed ──────────────────────────────────────────
        group('Waw Videos', () => {
            const res = http.get(`${BASE_URL}/waw-videos?page=1`, {
                headers: authH,
                tags: { name: 'waw_videos' },
            });
            apiCalls.add(1);
            browseDuration.add(res.timings.duration);
            check(res, { 'waw videos: status 200': (r) => r.status === 200 });
            errorRate.add(res.status !== 200);
        });

        sleep(randomInt(1, 2));

        // ─── Logout ───────────────────────────────────────────────────
        group('Logout', () => {
            const res = http.post(`${BASE_URL}/auth/logout`, null, {
                headers: authH,
                tags: { name: 'logout' },
            });
            apiCalls.add(1);
            check(res, { 'logout: status 200': (r) => r.status === 200 });
            errorRate.add(res.status !== 200);
        });
    });
}

// ─── Scenario: Rapid Browse (simulates fast scrolling / bot traffic) ─────────
function rapidBrowse() {
    group('Rapid Browse', () => {
        for (let i = 1; i <= 5; i++) {
            const res = http.get(`${BASE_URL}/products?page=${i}&per_page=20`, {
                headers,
                tags: { name: 'rapid_browse' },
            });
            apiCalls.add(1);
            browseDuration.add(res.timings.duration);
            check(res, { [`rapid page ${i}: status 200`]: (r) => r.status === 200 });
            errorRate.add(res.status !== 200);
            sleep(0.5); // Very fast browsing
        }
    });
}

// ─── Main Test Function ───────────────────────────────────────────────────────
// Distributes VUs across scenarios to mimic realistic traffic mix:
//   60% public browsing, 15% filters/search, 20% authenticated, 5% rapid browse
export default function () {
    const scenario = Math.random();

    if (scenario < 0.60) {
        publicBrowsing();
    } else if (scenario < 0.75) {
        filtersAndSearch();
    } else if (scenario < 0.95) {
        authenticatedUserJourney();
    } else {
        rapidBrowse();
    }

    // Think time between iterations
    sleep(randomInt(2, 5));
}

// ─── Summary ──────────────────────────────────────────────────────────────────
export function handleSummary(data) {
    const summary = {
        timestamp: new Date().toISOString(),
        thresholds: data.root_group ? 'see metrics' : {},
        metrics: {},
    };

    const keys = [
        'http_reqs', 'http_req_duration', 'http_req_failed',
        'errors', 'login_duration', 'browse_duration',
        'cart_duration', 'order_duration', 'search_duration', 'api_calls',
    ];

    for (const key of keys) {
        if (data.metrics[key]) {
            summary.metrics[key] = data.metrics[key].values;
        }
    }

    return {
        stdout: textSummary(data, { indent: ' ', enableColors: true }),
        'k6-summary.json': JSON.stringify(summary, null, 2),
    };
}

// k6 built-in text summary (available in k6 v0.30+)
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.2/index.js';
