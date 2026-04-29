import { router } from '@inertiajs/react';
import { useRef } from 'react';

type Photo = {
    id: number;
    path: string;
    thumbnail_path: string | null;
    position: number;
    is_primary: boolean;
};

export function PhotoUploader({ itemId, photos }: { itemId: number; photos: Photo[] }) {
    const inputRef = useRef<HTMLInputElement>(null);

    const upload = (file: File) => {
        router.post(
            `/items/${itemId}/photos`,
            { photo: file },
            { forceFormData: true, preserveScroll: true }
        );
    };

    const remove = (photoId: number) => {
        router.delete(`/items/${itemId}/photos/${photoId}`, { preserveScroll: true });
    };

    return (
        <div className="space-y-2">
            <div className="grid grid-cols-3 gap-2">
                {photos.map((p) => (
                    <div key={p.id} className="relative border rounded overflow-hidden">
                        <img
                            src={`/storage/${p.thumbnail_path ?? p.path}`}
                            alt=""
                            className="w-full h-32 object-cover"
                        />
                        {p.is_primary && (
                            <span className="absolute top-1 left-1 bg-black/70 text-white text-xs px-1.5 py-0.5 rounded">
                                Primary
                            </span>
                        )}
                        <button
                            type="button"
                            onClick={() => remove(p.id)}
                            className="absolute top-1 right-1 bg-white/90 text-xs px-1.5 py-0.5 rounded"
                        >
                            Remove
                        </button>
                    </div>
                ))}
            </div>

            <input
                ref={inputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) upload(file);
                    e.target.value = '';
                }}
            />
            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                className="border border-dashed rounded px-3 py-2 text-sm w-full"
            >
                Add photo
            </button>
        </div>
    );
}
