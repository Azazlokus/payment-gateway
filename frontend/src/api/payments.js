import axios from 'axios';

const http = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
});

export const paymentsApi = {
    list(params = {}) {
        return http.get('/payments', { params });
    },

    get(id) {
        return http.get(`/payments/${id}`);
    },

    create(data, idempotencyKey = null) {
        const headers = {};
        if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;
        return http.post('/payments', data, { headers });
    },

    cancel(id) {
        return http.post(`/payments/${id}/cancel`);
    },

    refund(id, data, idempotencyKey = null) {
        const headers = {};
        if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;
        return http.post(`/payments/${id}/refund`, data, { headers });
    },

    sync(id) {
        return http.post(`/payments/${id}/sync`);
    },
};

export const cryptoApi = {
    createDeposit(data) {
        return http.post('/crypto/deposits', data);
    },

    getDeposit(id) {
        return http.get(`/crypto/deposits/${id}`);
    },
};
