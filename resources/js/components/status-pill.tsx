type Status = 'draft' | 'ready' | 'listed' | 'reserved' | 'sold' | 'withdrawn';

const TONE: Record<Status, string> = {
    draft: 'bg-zinc-200 text-zinc-800',
    ready: 'bg-blue-100 text-blue-800',
    listed: 'bg-emerald-100 text-emerald-800',
    reserved: 'bg-amber-100 text-amber-900',
    sold: 'bg-violet-100 text-violet-800',
    withdrawn: 'bg-rose-100 text-rose-800',
};

export function StatusPill({ status }: { status: Status }) {
    return (
        <span className={`inline-block text-xs font-medium px-2 py-0.5 rounded-full ${TONE[status]}`}>
            {status}
        </span>
    );
}
