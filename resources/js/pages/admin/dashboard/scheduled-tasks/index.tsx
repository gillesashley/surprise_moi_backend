import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AlertCircle, Calendar, Clock, RefreshCw } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Scheduled Tasks',
        href: '/dashboard/scheduled-tasks',
    },
];

interface ScheduleTask {
    command: string;
    frequency: string;
    next_due: string;
    overdue: string;
    raw_line: string;
}

interface Props {
    scheduleData: ScheduleTask[];
    lastRefreshed: string;
    success: boolean;
    error: string | null;
    cached: boolean;
}

export default function Index({
    scheduleData,
    lastRefreshed,
    success,
    error,
    cached,
}: Props) {
    const [refreshing, setRefreshing] = useState(false);

    const handleRefresh = () => {
        setRefreshing(true);
        router.reload({
            onFinish: () => {
                setRefreshing(false);
            },
        });
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString();
    };

    const isOverdue = (overdue: string) => {
        return overdue && overdue.toLowerCase() !== 'no' && overdue !== '';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Scheduled Tasks" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">
                            Scheduled Tasks
                        </h1>
                        <p className="text-gray-600">
                            Monitor Laravel scheduled tasks and their execution
                            times
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {cached && (
                            <Badge variant="outline" className="bg-green-50">
                                Cached
                            </Badge>
                        )}
                        <Button
                            onClick={handleRefresh}
                            disabled={refreshing}
                            variant="outline"
                        >
                            <RefreshCw
                                className={`mr-2 h-4 w-4 ${
                                    refreshing ? 'animate-spin' : ''
                                }`}
                            />
                            Refresh
                        </Button>
                    </div>
                </div>

                <div className="text-sm text-gray-500">
                    Last refreshed: {formatDate(lastRefreshed)}
                </div>

                {error && (
                    <Card className="border-red-200 bg-red-50">
                        <CardContent className="pt-6">
                            <div className="flex items-start gap-3">
                                <AlertCircle className="h-5 w-5 text-red-500" />
                                <div>
                                    <p className="font-medium text-red-800">
                                        Failed to fetch schedule data
                                    </p>
                                    <p className="text-sm text-red-600">
                                        {error}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {success && !error && (
                    <>
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">
                                        Total Tasks
                                    </CardTitle>
                                    <Calendar className="h-4 w-4 text-blue-600" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {scheduleData.length}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">
                                        Overdue Tasks
                                    </CardTitle>
                                    <AlertCircle className="h-4 w-4 text-red-600" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {
                                            scheduleData.filter((task) =>
                                                isOverdue(task.overdue),
                                            ).length
                                        }
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">
                                        Next Due
                                    </CardTitle>
                                    <Clock className="h-4 w-4 text-green-600" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-sm font-medium">
                                        {scheduleData[0]?.next_due || 'N/A'}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle>Schedule List</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="min-h-96 rounded-lg border">
                                    {scheduleData.length === 0 ? (
                                        <div className="flex h-96 items-center justify-center">
                                            <p className="text-gray-500">
                                                No scheduled tasks found
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="divide-y">
                                            {scheduleData.map((task, index) => (
                                                <div
                                                    key={index}
                                                    className={`flex items-center justify-between p-4 hover:bg-gray-50 ${
                                                        isOverdue(task.overdue)
                                                            ? 'bg-red-50'
                                                            : ''
                                                    }`}
                                                >
                                                    <div className="flex items-center space-x-4">
                                                        <div
                                                            className={`flex h-10 w-10 items-center justify-center rounded-full ${
                                                                isOverdue(
                                                                    task.overdue,
                                                                )
                                                                    ? 'bg-red-100'
                                                                    : 'bg-blue-100'
                                                            }`}
                                                        >
                                                            <Calendar
                                                                className={`h-5 w-5 ${
                                                                    isOverdue(
                                                                        task.overdue,
                                                                    )
                                                                        ? 'text-red-600'
                                                                        : 'text-blue-600'
                                                                }`}
                                                            />
                                                        </div>
                                                        <div>
                                                            <div className="flex items-center space-x-2">
                                                                <span className="font-medium">
                                                                    {
                                                                        task.command
                                                                    }
                                                                </span>
                                                                {isOverdue(
                                                                    task.overdue,
                                                                ) && (
                                                                    <Badge className="bg-red-100 text-red-800">
                                                                        Overdue
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            <div className="text-sm text-gray-500">
                                                                {task.frequency}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div className="text-right">
                                                        <div className="text-sm font-medium">
                                                            {task.next_due}
                                                        </div>
                                                        {task.overdue &&
                                                            task.overdue.toLowerCase() !==
                                                                'no' && (
                                                                <div className="text-xs text-red-500">
                                                                    {
                                                                        task.overdue
                                                                    }
                                                                </div>
                                                            )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
