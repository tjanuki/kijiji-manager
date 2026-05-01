<?php

namespace App\Services;

use App\Models\Item;

class ListingDraftRenderer
{
    /**
     * Render the Kijiji-ready listing draft for an Item.
     *
     * Caller is expected to have eager-loaded `user.settings` on the Item
     * (e.g. `$item->load('user.settings')`). Without that, this method
     * triggers two lazy queries; harmless for one-off rendering on the
     * show page but a textbook N+1 in batch contexts.
     *
     * @return array{title: string, description: string}
     */
    public function render(Item $item): array
    {
        return [
            'title' => $this->renderTitle($item),
            'description' => $this->renderDescription($item),
        ];
    }

    /**
     * Title is `"<category> - <title> - <condition label>"`, with null
     * `category` filtered out. `condition` is required by the schema and
     * therefore not guarded against null.
     */
    private function renderTitle(Item $item): string
    {
        return implode(' - ', array_filter([
            $item->category,
            $item->title,
            $item->condition->label(),
        ]));
    }

    private function renderDescription(Item $item): string
    {
        $sections = [];

        if (filled($item->description)) {
            $sections[] = trim($item->description);
        }

        $sections[] = 'Condition: '.$item->condition->label();

        $snippets = $item->user?->settings?->snippets ?? [];

        if (filled($snippets['pickup'] ?? null)) {
            $sections[] = 'Pickup: '.trim($snippets['pickup']);
        }

        if (filled($snippets['payment'] ?? null)) {
            $sections[] = 'Payment: '.trim($snippets['payment']);
        }

        return implode("\n\n", $sections);
    }
}
