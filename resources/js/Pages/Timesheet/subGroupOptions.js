import { Construction, Hammer, Briefcase, Wrench, Building2, Tag } from 'lucide-react';

export const SUB_GROUP_OPTIONS = {
    khawista: [
        { value: 'construction', icon: Construction, label: 'Construction' },
        { value: 'piling',       icon: Hammer,       label: 'Piling' },
    ],
    giam: [
        { value: 'staff',        icon: Briefcase,    label: 'Staff' },
        { value: 'mechanical',   icon: Wrench,       label: 'Mechanical' },
        { value: 'construction', icon: Construction, label: 'Construction' },
    ],
    ho: [
        { value: 'HO-1', icon: Building2, label: 'HO-1' },
        { value: 'HO-2', icon: Building2, label: 'HO-2' },
    ],
};

export function subGroupMeta(projectKode, value) {
    const opts = SUB_GROUP_OPTIONS[(projectKode || '').toLowerCase()] || [];
    return opts.find(o => o.value === value) || {
        icon: Tag,
        label: value ? value.charAt(0).toUpperCase() + value.slice(1) : '',
    };
}
