import { Head, Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

export default function Welcome({ canRegister = true }: { canRegister?: boolean }) {
    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-svh flex-col items-center justify-center bg-background p-6">
                <div className="flex w-full max-w-sm flex-col items-center gap-8 text-center">
                    <div className="flex flex-col items-center gap-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                            <AppLogoIcon className="size-7 fill-current text-white dark:text-black" />
                        </div>
                        <h1 className="text-2xl font-semibold">Kijiji Manager</h1>
                        <p className="text-sm text-muted-foreground">
                            Track inventory, draft listings, and schedule pickups for your moving sale.
                        </p>
                    </div>

                    <div className="flex w-full flex-col gap-2">
                        <Button asChild className="w-full">
                            <Link href={login()}>Log in</Link>
                        </Button>
                        {canRegister && (
                            <Button asChild variant="outline" className="w-full">
                                <Link href={register()}>Create an account</Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
