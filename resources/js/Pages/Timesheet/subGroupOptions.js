import { Construction, Hammer, Briefcase, Wrench, Building2, Tag } from 'lucide-react';

export const SUB_GROUP_OPTIONS = {
    khawista: [
        { value: 'construction', icon: Construction, emoji: '🏗️', label: 'Construction' },
        { value: 'piling',       icon: Hammer,       emoji: '🔩', label: 'Piling' },
    ],
    giam: [
        { value: 'staff',        icon: Briefcase,    emoji: '🧑‍💼', label: 'Staff' },
        { value: 'mechanical',   icon: Wrench,       emoji: '🔧', label: 'Mechanical' },
        { value: 'construction', icon: Construction, emoji: '🏗️', label: 'Construction' },
    ],
    ho: [
        { value: 'HO-1', icon: Building2, emoji: '🏢', label: 'HO-1' },
        { value: 'HO-2', icon: Building2, emoji: '🏢', label: 'HO-2' },
    ],
};

export function subGroupMeta(projectKode, value) {
    const opts = SUB_GROUP_OPTIONS[(projectKode || '').toLowerCase()] || [];
    return opts.find(o => o.value === value) || {
        icon: Tag,
        emoji: '🏷️',
        label: value ? value.charAt(0).toUpperCase() + value.slice(1) : '',
    };
}
