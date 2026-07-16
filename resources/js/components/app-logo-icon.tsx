import { useId } from 'react';
import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGSVGElement>) {
    const id = useId().replaceAll(':', '');
    const ribbon = `${id}-ribbon`;
    const leftHead = `${id}-left-head`;
    const rightHead = `${id}-right-head`;
    const softShadow = `${id}-soft-shadow`;
    const crossoverShadow = `${id}-crossover-shadow`;

    return (
        <svg
            {...props}
            viewBox="0 0 800 500"
            xmlns="http://www.w3.org/2000/svg"
            role={props['aria-label'] ? 'img' : undefined}
            aria-hidden={props['aria-label'] ? undefined : true}
        >
            <defs>
                <linearGradient
                    id={ribbon}
                    x1="15%"
                    y1="20%"
                    x2="85%"
                    y2="80%"
                    gradientUnits="userSpaceOnUse"
                >
                    <stop offset="0%" stopColor="#0284c7" />
                    <stop offset="25%" stopColor="#0ea5e9" />
                    <stop offset="45%" stopColor="#0d9488" />
                    <stop offset="65%" stopColor="#10b981" />
                    <stop offset="100%" stopColor="#059669" />
                </linearGradient>
                <linearGradient
                    id={leftHead}
                    x1="0%"
                    y1="0%"
                    x2="100%"
                    y2="100%"
                >
                    <stop offset="0%" stopColor="#38bdf8" />
                    <stop offset="100%" stopColor="#0284c7" />
                </linearGradient>
                <linearGradient
                    id={rightHead}
                    x1="0%"
                    y1="0%"
                    x2="100%"
                    y2="100%"
                >
                    <stop offset="0%" stopColor="#34d399" />
                    <stop offset="100%" stopColor="#059669" />
                </linearGradient>
                <filter
                    id={softShadow}
                    x="-20%"
                    y="-20%"
                    width="140%"
                    height="140%"
                >
                    <feDropShadow
                        dx="3"
                        dy="6"
                        stdDeviation="8"
                        floodColor="#0f172a"
                        floodOpacity="0.15"
                    />
                </filter>
                <filter
                    id={crossoverShadow}
                    x="-30%"
                    y="-30%"
                    width="160%"
                    height="160%"
                >
                    <feDropShadow
                        dx="-4"
                        dy="4"
                        stdDeviation="6"
                        floodColor="#022c22"
                        floodOpacity="0.25"
                    />
                </filter>
            </defs>
            <g transform="translate(0, 15)">
                <path
                    d="M 237.5,216.4 A 85,85 0 1,0 340.1,350.1 L 459.9,229.9 A 85,85 0 0,1 477.5,216.4"
                    fill="none"
                    stroke={`url(#${ribbon})`}
                    strokeWidth="44"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                <path
                    d="M 562.5,216.4 A 85,85 0 1,1 459.9,350.1 L 340.1,229.9 A 85,85 0 0,0 322.5,216.4"
                    fill="none"
                    stroke={`url(#${ribbon})`}
                    strokeWidth="44"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    filter={`url(#${crossoverShadow})`}
                />
                <circle
                    cx="280"
                    cy="132"
                    r="34"
                    fill={`url(#${leftHead})`}
                    filter={`url(#${softShadow})`}
                />
                <circle
                    cx="520"
                    cy="132"
                    r="34"
                    fill={`url(#${rightHead})`}
                    filter={`url(#${softShadow})`}
                />
            </g>
        </svg>
    );
}
