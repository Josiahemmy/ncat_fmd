import { Head } from '@inertiajs/react';
import { Plane } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { FleetCard } from '@/Components/aircraft/FleetCard';

export default function Fleet({ types = [] }) {
    const totalAircraft = types.reduce((n, t) => n + (t.fleet_count ?? 0), 0);

    return (
        <AppLayout>
            <Head title="Aircraft Types" />

            <PageHeader
                icon={Plane}
                eyebrow="Fleet"
                title="Aircraft Types"
                description={`The department's ${totalAircraft} aircraft across ${types.length} types. Select a type to reveal its registrations, then open an aircraft to work.`}
            />

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                {types.map((type, i) => (
                    <FleetCard key={type.id} type={type} index={i} />
                ))}
            </div>
        </AppLayout>
    );
}
