<script setup lang="ts">
import { computed } from "vue";
import type { SubjectAnalysis } from "./types";
const props = defineProps<{ subject: SubjectAnalysis }>();
const max = computed(() =>
    props.subject.normalized ? 100 : Number(props.subject.max_score) || 100,
);
const coords = computed(() =>
    props.subject.points.map((p, i) => ({
        ...p,
        x:
            42 +
            (props.subject.points.length === 1
                ? 240
                : (i / (props.subject.points.length - 1)) * 480),
        y: 190 - (p.value / max.value) * 150,
    })),
);
</script>
<template>
    <div class="trend">
        <svg
            v-if="coords.length"
            viewBox="0 0 560 240"
            role="img"
            :aria-label="subject.name + '成绩趋势，单位' + subject.unit"
        >
            <g v-for="tick in [0, 0.5, 1]" :key="tick">
                <line
                    x1="42"
                    x2="530"
                    :y1="190 - tick * 150"
                    :y2="190 - tick * 150"
                    stroke="#e6ebe2"
                    stroke-dasharray="4 5"
                />
                <text x="5" :y="194 - tick * 150" fill="#859183" font-size="11">
                    {{ (max * tick).toFixed(0) }}
                </text>
            </g>
            <polyline
                :points="coords.map((p) => `${p.x},${p.y}`).join(' ')"
                fill="none"
                stroke="#235e48"
                stroke-width="3"
                stroke-linejoin="round"
            />
            <g v-for="(p, i) in coords" :key="i">
                <circle
                    :cx="p.x"
                    :cy="p.y"
                    r="5"
                    fill="#235e48"
                    stroke="white"
                    stroke-width="2"
                >
                    <title>
                        {{ p.title }} · {{ p.date }}：{{ p.score }} /
                        {{ p.max_score }}（{{ p.rate }}%）
                    </title>
                </circle>
                <text
                    :x="p.x"
                    :y="p.y - 13"
                    text-anchor="middle"
                    fill="#235e48"
                    font-size="11"
                >
                    {{ p.value }}
                </text>
                <text
                    v-if="
                        coords.length <= 6 || i === 0 || i === coords.length - 1
                    "
                    :x="p.x"
                    y="220"
                    text-anchor="middle"
                    fill="#859183"
                    font-size="10"
                >
                    {{ p.date.slice(5) }}
                </text>
            </g>
        </svg>
        <div v-else class="empty">这个科目暂时没有分数成绩</div>
        <p class="hint">
            {{
                subject.normalized
                    ? "满分不同，按得分率比较"
                    : "相同满分，按原始分数比较"
            }}
            · 等级另行统计
        </p>
    </div>
</template>
