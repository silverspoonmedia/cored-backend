<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ExternalLinkKind;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\ExternalLink;
use Illuminate\Database\Eloquent\Collection;

/**
 * Creator-scoped external / bio links (social, donation, website, other).
 * Membership is private — this service never exposes co-ownership across profiles.
 */
class ExternalLinkService
{
    public function __construct(
        private readonly CreatorAccessService $creatorAccess,
    ) {}

    /**
     * @return Collection<int, ExternalLink>
     */
    public function listForCreator(User $actor, string $creatorId): Collection
    {
        $creator = $this->creatorAccess->findOrFail($creatorId);
        $this->creatorAccess->assertCanView($creator, $actor);

        return $creator->externalLinks()
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  array{label?: string|null, url: string, kind?: string|null, sort_order?: int|null}  $data
     */
    public function create(User $actor, string $creatorId, array $data): ExternalLink
    {
        $creator = $this->creatorAccess->findOrFail($creatorId);
        $this->creatorAccess->assertCanMutate($creator, $actor);

        $url = trim((string) ($data['url'] ?? ''));
        if ($url === '') {
            throw new VtualException(__('api/vtual.link.url_required'), 422);
        }

        $kind = $this->parseKind($data['kind'] ?? null);
        $sort = array_key_exists('sort_order', $data) && $data['sort_order'] !== null
            ? (int) $data['sort_order']
            : (int) $creator->externalLinks()->max('sort_order') + 1;

        // Column is non-nullable; empty string when label omitted.
        $label = $this->nullableString($data['label'] ?? null) ?? '';

        /** @var ExternalLink $link */
        $link = $creator->externalLinks()->create([
            'label' => $label,
            'url' => $url,
            'kind' => $kind,
            'sort_order' => $sort,
        ]);

        return $link;
    }

    /**
     * @param  array{label?: string|null, url?: string|null, kind?: string|null, sort_order?: int|null}  $data
     */
    public function update(User $actor, string $linkId, array $data): ExternalLink
    {
        $link = $this->findCreatorLinkOrFail($linkId);
        $this->creatorAccess->assertCanMutate($link->linkable, $actor);

        $payload = [];
        if (array_key_exists('label', $data)) {
            $payload['label'] = $this->nullableString($data['label']) ?? '';
        }
        if (array_key_exists('url', $data) && $data['url'] !== null) {
            $url = trim((string) $data['url']);
            if ($url === '') {
                throw new VtualException(__('api/vtual.link.url_required'), 422);
            }
            $payload['url'] = $url;
        }
        if (array_key_exists('kind', $data) && $data['kind'] !== null) {
            $payload['kind'] = $this->parseKind($data['kind']);
        }
        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
            $payload['sort_order'] = (int) $data['sort_order'];
        }

        if ($payload !== []) {
            $link->forceFill($payload)->save();
        }

        return $link->fresh() ?? $link;
    }

    public function delete(User $actor, string $linkId): void
    {
        $link = $this->findCreatorLinkOrFail($linkId);
        $this->creatorAccess->assertCanMutate($link->linkable, $actor);
        $link->delete();
    }

    private function findCreatorLinkOrFail(string $linkId): ExternalLink
    {
        $link = ExternalLink::query()->findOrFail($linkId);
        if (! $link->linkable instanceof Creator) {
            throw new VtualException(__('api/vtual.link.not_creator'), 422);
        }

        $link->setRelation('linkable', $link->linkable);

        return $link;
    }

    private function parseKind(mixed $raw): ExternalLinkKind
    {
        if ($raw === null || $raw === '') {
            return ExternalLinkKind::Other;
        }

        $value = is_string($raw) ? $raw : (string) $raw;

        return ExternalLinkKind::tryFrom($value) ?? ExternalLinkKind::Other;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
