import React from 'react';
import {
    BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer,
    CartesianGrid, Cell, Legend
} from 'recharts';
import { PROJECT_COLORS } from '@/Constants/projectColors';

const formatRupiahShort = (val) => {
    if (val >= 1_000_000_000) return `${(val / 1_000_000_000).toFixed(1)}M`;
    if (val >= 1_000_000)     return `${(val / 1_000_000).toFixed(0)}jt`;
    if (val >= 1_000)         return `${(val / 1_000).toFixed(0)}rb`;
    return `${val}`;
};

const formatRupiahFull = (val) =>
    'Rp ' + Number(val).toLocaleString('id-ID');

const tooltipStyle = {
    background: 'var(--card)',
    border: '1px solid var(--border)',
    borderRadius: 8,
    fontSize: 12,
    padding: '8px 12px',
    color: 'var(--text)',
};

// Custom tooltip untuk stacked bar — biar tampilkan breakdown per project
function StackedTooltip({ active, payload, label }) {
    if (!active || !payload || !payload.length) return null;

    // Filter hanya yg value > 0
    const items = payload.filter(p => p.value > 0);
    const total = items.reduce((acc, p) => acc + p.value, 0);

    return (
        <div style={tooltipStyle}>
            <div style={{fontWeight:600, marginBottom:6, color:'var(--text)'}}>Bulan: {label}</div>
            {items.map((item, i) => (
                <div key={i} style={{display:'flex', alignItems:'center', gap:6, fontSize:11, marginBottom:2}}>
                    <span style={{width:10, height:10, borderRadius:2, background:item.fill, display:'inline-block'}}/>
                    <span style={{color:'#8A90A8'}}>{item.name}:</span>
                    <span style={{marginLeft:'auto', fontWeight:600}}>{formatRupiahFull(item.value)}</span>
                </div>
            ))}
            <div style={{borderTop:'1px solid var(--border)', marginTop:6, paddingTop:4, fontSize:11.5, fontWeight:700, display:'flex', justifyContent:'space-between'}}>
                <span>Total:</span>
                <span style={{color:'#E8A020'}}>{formatRupiahFull(total)}</span>
            </div>
        </div>
    );
}

export function KaryawanPerProjectChart({ data = [] }) {
    return (
        <ResponsiveContainer width="100%" height={280}>
            <BarChart data={data} margin={{ top: 10, right: 16, left: -12, bottom: 5 }} barCategoryGap="20%">
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border2)" />
                <XAxis
                    dataKey="project"
                    tick={{ fontSize: 11, fill: '#8A90A8' }}
                    axisLine={{ stroke: 'var(--border2)' }}
                    tickLine={false}
                />
                <YAxis
                    tick={{ fontSize: 11, fill: '#8A90A8' }}
                    axisLine={{ stroke: 'var(--border2)' }}
                    tickLine={false}
                    allowDecimals={false}
                />
                <Tooltip
                    trigger="item"
                    contentStyle={tooltipStyle}
                    cursor={false}
                    formatter={(value) => [`${value} karyawan`, 'Total']}
                    labelFormatter={(label) => `Project: ${label}`}
                />
                <Bar dataKey="total" radius={[6, 6, 0, 0]} maxBarSize={110}>
                    {data.map((entry, i) => (
                        <Cell
                            key={`cell-${i}`}
                            fill={PROJECT_COLORS[entry.project] || '#6b7280'}
                        />
                    ))}
                </Bar>
            </BarChart>
        </ResponsiveContainer>
    );
}

export function LengthOfServiceChart({ data = [] }) {
    return (
        <ResponsiveContainer width="100%" height={300}>
            <BarChart data={data} margin={{ top: 10, right: 16, left: -12, bottom: 5 }} barCategoryGap="20%">
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border2)" />
                <XAxis
                    dataKey="label"
                    tick={{ fontSize: 10.5, fill: '#8A90A8' }}
                    axisLine={{ stroke: 'var(--border2)' }}
                    tickLine={false}
                />
                <YAxis
                    tick={{ fontSize: 11, fill: '#8A90A8' }}
                    axisLine={{ stroke: 'var(--border2)' }}
                    tickLine={false}
                    allowDecimals={false}
                />
                <Tooltip
                    trigger="item"
                    contentStyle={tooltipStyle}
                    cursor={false}
                    formatter={(value) => [`${value} karyawan`, 'Total']}
                    labelFormatter={(label) => `Masa kerja: ${label}`}
                />
                <Bar dataKey="total" fill="#3A8FE0" radius={[6, 6, 0, 0]} maxBarSize={90} />
            </BarChart>
        </ResponsiveContainer>
    );
}

export function PengeluaranGajiChart({ data = [], projectKeys = [], isMultiProject = false }) {
    // Single project mode → 1 bar warna kuning per bulan (dataKey="total")
    // Multi project mode → stacked bar per project (dataKey=nama project)
    const stackMode = isMultiProject && projectKeys.length > 1;

    return (
        <ResponsiveContainer width="100%" height={stackMode ? 320 : 280}>
            <BarChart data={data} margin={{ top: 10, right: 16, left: 0, bottom: 5 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border2)" />
                <XAxis
                    dataKey="label"
                    tick={{ fontSize: 10, fill: '#8A90A8' }}
                    axisLine={{ stroke: 'var(--border2)' }}
                    tickLine={false}
                    interval="preserveStartEnd"
                />
                <YAxis
                    tick={{ fontSize: 10, fill: '#8A90A8' }}
                    axisLine={{ stroke: 'var(--border2)' }}
                    tickLine={false}
                    tickFormatter={formatRupiahShort}
                />
                {stackMode ? (
                    <>
                        <Tooltip
                            trigger="item"
                            content={<StackedTooltip />}
                            cursor={false}
                        />
                        <Legend
                            wrapperStyle={{ fontSize: 11, paddingTop: 8 }}
                            iconType="circle"
                            iconSize={8}
                        />
                        {projectKeys.map((key, i) => {
                            const isLast = i === projectKeys.length - 1;
                            return (
                                <Bar
                                    key={key}
                                    dataKey={key}
                                    stackId="gaji"
                                    fill={PROJECT_COLORS[key] || '#6b7280'}
                                    radius={isLast ? [6, 6, 0, 0] : [0, 0, 0, 0]}
                                    maxBarSize={40}
                                />
                            );
                        })}
                    </>
                ) : (
                    <>
                        <Tooltip
                            trigger="item"
                            contentStyle={tooltipStyle}
                            cursor={false}
                            formatter={(value) => [formatRupiahFull(value), 'Total Gaji']}
                            labelFormatter={(label) => `Bulan: ${label}`}
                        />
                        <Bar
                            dataKey="total"
                            fill="#E8A020"
                            radius={[6, 6, 0, 0]}
                            maxBarSize={40}
                        />
                    </>
                )}
            </BarChart>
        </ResponsiveContainer>
    );
}