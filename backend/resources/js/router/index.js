import { createRouter, createWebHistory } from 'vue-router';
import DashboardPage from '@/pages/DashboardPage.vue';
import CreatePaymentPage from '@/pages/CreatePaymentPage.vue';
import PaymentDetailPage from '@/pages/PaymentDetailPage.vue';
import MetricsDashboardPage from '@/pages/MetricsDashboardPage.vue';
import RecurringPage from '@/pages/RecurringPage.vue';
import AuditLogPage from '@/pages/AuditLogPage.vue';
import WebhookLogsPage from '@/pages/WebhookLogsPage.vue';
import PaymentLinksPage from '@/pages/PaymentLinksPage.vue';

const routes = [
    { path: '/', component: DashboardPage, name: 'dashboard' },
    { path: '/payments/create', component: CreatePaymentPage, name: 'payments.create' },
    { path: '/payments/:id', component: PaymentDetailPage, name: 'payments.show', props: true },
    { path: '/metrics', component: MetricsDashboardPage, name: 'metrics' },
    { path: '/recurring', component: RecurringPage, name: 'recurring' },
    { path: '/audit-log', component: AuditLogPage, name: 'audit-log' },
    { path: '/webhook-logs', component: WebhookLogsPage, name: 'webhook-logs' },
    { path: '/payment-links', component: PaymentLinksPage, name: 'payment-links' },
];

export default createRouter({
    history: createWebHistory(),
    routes,
});
