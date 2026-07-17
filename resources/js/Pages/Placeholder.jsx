import { Head } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Compass, Construction } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card } from '@/Components/ui/Card';
import { Badge } from '@/Components/ui/Badge';

/**
 * Placeholder — branded "coming in a later phase" screen for sidebar modules
 * that are routed today but built in a subsequent phase.
 */
export default function Placeholder({ module = 'This module', phase = 'a later phase' }) {
    return (
        <AppLayout>
            <Head title={module} />

            <div className="flex min-h-[62vh] items-center justify-center">
                <motion.div
                    initial={{ opacity: 0, y: 14 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
                    className="w-full max-w-xl"
                >
                    <Card variant="glass" className="relative overflow-hidden px-8 py-12 text-center">
                        <div className="pointer-events-none absolute -right-16 -top-16 size-52 rounded-full bg-ncat-cyan/10 blur-3xl" />
                        <div className="pointer-events-none absolute -bottom-20 -left-16 size-52 rounded-full bg-ncat-blue/10 blur-3xl" />

                        <div className="relative mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-ncat-navy text-white shadow-glass">
                            <Construction className="size-7" />
                        </div>

                        <Badge variant="default" className="mb-4">Planned · {phase}</Badge>

                        <h1 className="font-display text-2xl font-bold tracking-tight text-ncat-navy">
                            {module}
                        </h1>
                        <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                            This module is part of the phased build-out. The foundation, design
                            system and navigation are live now — {module} arrives in {phase}.
                        </p>

                        <div className="mt-8 flex items-center justify-center gap-2 text-xs text-muted-foreground">
                            <Compass className="size-3.5 text-primary" />
                            Use the sidebar to explore the rest of the console.
                        </div>
                    </Card>
                </motion.div>
            </div>
        </AppLayout>
    );
}
