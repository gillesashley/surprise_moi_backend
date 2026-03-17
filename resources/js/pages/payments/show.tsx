import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payments', href: '/dashboard/payments' },
    { title: 'Payment Detail', href: '#' },
];

export default function PaymentShow() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Detail" />
            <div>Payment show placeholder</div>
        </AppLayout>
    );
}
