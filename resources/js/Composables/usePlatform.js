export const PLATFORM_COLORS = {
    facebook:  '#1877F2',
    instagram: '#E1306C',
    tiktok:    '#010101',
    x:         '#000000',
    linkedin:  '#0A66C2',
}

export const PLATFORM_LABELS = {
    facebook:  'Facebook',
    instagram: 'Instagram',
    tiktok:    'TikTok',
    x:         'X (Twitter)',
    linkedin:  'LinkedIn',
}

export const STATUS_CONFIG = {
    draft:                { label: 'Draft',              class: 'badge-draft' },
    pending_approval:     { label: 'Pending Approval',   class: 'badge-pending' },
    approved:             { label: 'Approved',           class: 'badge-published' },
    scheduled:            { label: 'Scheduled',          class: 'badge-scheduled' },
    publishing:           { label: 'Publishing',         class: 'badge-publishing' },
    published:            { label: 'Published',          class: 'badge-published' },
    partially_published:  { label: 'Partial',            class: 'badge-partial' },
    failed:               { label: 'Failed',             class: 'badge-failed' },
    cancelled:            { label: 'Cancelled',          class: 'badge-cancelled' },
    queued:               { label: 'Queued',             class: 'badge-scheduled' },
    valid:                { label: 'Valid',              class: 'badge-published' },
    invalid:              { label: 'Invalid',            class: 'badge-failed' },
}

export function usePlatform() {
    function getPlatformColor(platform) {
        return PLATFORM_COLORS[platform] ?? '#6b7280'
    }

    function getPlatformLabel(platform) {
        return PLATFORM_LABELS[platform] ?? platform
    }

    function getStatusConfig(status) {
        return STATUS_CONFIG[status] ?? { label: status, class: 'badge-draft' }
    }

    return { getPlatformColor, getPlatformLabel, getStatusConfig, PLATFORM_COLORS, PLATFORM_LABELS }
}
