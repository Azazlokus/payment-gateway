import { createRouter, createWebHistory } from 'vue-router';
import DashboardPage from '@/pages/DashboardPage.vue';
import CreatePaymentPage from '@/pages/CreatePaymentPage.vue';
import PaymentDetailPage from '@/pages/PaymentDetailPage.vue';
import MetricsDashboardPage from '@/pages/MetricsDashboardPage.vue';
import RecurringPage from '@/pages/RecurringPage.vue';

const routes = [
    { path: '/', component: DashboardPage, name: 'dashboard' },
    { path: '/payments/create', component: CreatePaymentPage, name: 'payments.create' },
    { path: '/payments/:id', component: PaymentDetailPage, name: 'payments.show', props: true },
    { path: '/metrics', component: MetricsDashboardPage, name: 'metrics' },
    { path: '/recurring', component: RecurringPage, name: 'recurring' },
];

export default createRouter({
    history: createWebHistory(),
    routes,
});
