<script setup lang="ts">
import { computed } from 'vue';
import Amount from '@/components/Amount.vue';
import type { Estimate, TokenRange } from '@/lib/pricing';

/** The itemised breakdown behind the price-range chip. */
const props = defineProps<{
    estimate: Estimate;
    promptCharacters: number;
    referenceImageCount: number;
    quality: string;
    size: string;
}>();

const tokens = (r: TokenRange) =>
    r.tokensLow === r.tokensHigh
        ? r.tokensLow.toLocaleString('en-US')
        : `${r.tokensLow.toLocaleString('en-US')}–${r.tokensHigh.toLocaleString('en-US')}`;

const lines = computed(() => {
    const e = props.estimate;

    return [
        {
            label: 'Prompt',
            note: `${tokens(e.prompt)} tokens · ${props.promptCharacters.toLocaleString('en-US')} characters ÷ 4, ±15%`,
            low: e.prompt.providerCostLow,
            high: e.prompt.providerCostHigh,
        },
        {
            label: 'Reference images',
            note: `${tokens(e.referenceImages)} tokens · ${props.referenceImageCount} × 765`,
            low: e.referenceImages.providerCostLow,
            high: e.referenceImages.providerCostHigh,
        },
        {
            label: 'Image output',
            note: `${tokens(e.imageOutput)} tokens · ${props.quality}, ${props.size}`,
            low: e.imageOutput.providerCostLow,
            high: e.imageOutput.providerCostHigh,
        },
        {
            label: 'Thinking',
            note: `${tokens(e.thinking)} tokens · ${props.quality} range`,
            low: e.thinking.providerCostLow,
            high: e.thinking.providerCostHigh,
        },
        {
            label: '25% service fee',
            note: 'added to Pixelforge’s cost',
            low: e.low - e.providerCostLow,
            high: e.high - e.providerCostHigh,
        },
    ];
});
</script>

<template>
    <div>
        <div class="text-xs text-muted-foreground">Estimated price</div>
        <div class="font-serif text-[28px] leading-[1.1] tracking-[-0.02em]">
            <Amount :nanodollars="estimate.low" rounding="down" />
            –
            <Amount :nanodollars="estimate.high" rounding="up" />
        </div>
        <p class="mt-1 mb-2.5 text-xs text-muted-foreground">
            Never more than
            <Amount :nanodollars="estimate.high" rounding="up" />. Charged only
            if it succeeds.
        </p>
        <dl class="m-0">
            <div
                v-for="line in lines"
                :key="line.label"
                class="grid grid-cols-[1fr_auto] gap-3 border-b border-border py-2 text-[13px] last:border-0"
            >
                <dt>
                    {{ line.label }}
                    <small
                        class="block font-mono text-[11px] text-muted-foreground"
                        >{{ line.note }}</small
                    >
                </dt>
                <dd class="m-0 text-right text-[12.5px] whitespace-nowrap">
                    <template v-if="line.low === line.high">
                        <Amount :nanodollars="line.low" />
                    </template>
                    <template v-else>
                        <Amount
                            :nanodollars="line.low"
                            rounding="down"
                        />–<Amount :nanodollars="line.high" rounding="up" />
                    </template>
                </dd>
            </div>
        </dl>
    </div>
</template>
