<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import Amount from '@/components/Amount.vue';
import EstimateReceipt from '@/components/EstimateReceipt.vue';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type {
    GenerationSettings,
    Quality,
    Size,
} from '@/lib/generationSettings';
import {
    filledReferenceImages,
    MAX_PROMPT_CHARACTERS,
    MAX_REFERENCE_IMAGES,
    problemsWith,
    QUALITIES,
    SIZES,
} from '@/lib/generationSettings';
import type { PricingRules } from '@/lib/pricing';
import { charge, estimate, promptCharacters } from '@/lib/pricing';
import { store } from '@/routes/generations';

const props = defineProps<{ pricing: PricingRules | null }>();

const settings = ref<GenerationSettings>({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    referenceImages: [],
});
/** Set once the user has typed or pressed Generate, so an empty bar isn't scolded on arrival. */
const touched = ref(false);
const open = ref({ size: false, quality: false, references: false });

const form = useForm({
    prompt: '',
    size: '1024x1024' as Size,
    quality: 'medium' as Quality,
    reference_images: [] as string[],
});

const characters = computed(() => promptCharacters(settings.value.prompt));
const references = computed(() => filledReferenceImages(settings.value));
const problems = computed(() => problemsWith(settings.value));

const estimateFor = (quality: Quality) =>
    props.pricing === null
        ? null
        : estimate(
              {
                  prompt: settings.value.prompt,
                  size: settings.value.size,
                  quality,
                  referenceImageCount: Math.min(
                      references.value.length,
                      MAX_REFERENCE_IMAGES,
                  ),
              },
              props.pricing,
          );
const current = computed(() => estimateFor(settings.value.quality));

const sizeOption = computed(
    () => SIZES.find((s) => s.value === settings.value.size) ?? SIZES[0],
);
const badReference = (index: number) =>
    problems.value.some(
        (p) => p.field === 'reference_images' && p.index === index,
    );
const referencesHaveProblem = computed(() =>
    problems.value.some((p) => p.field === 'reference_images'),
);

/** Server errors only show when the bar itself finds nothing wrong. */
const serverMessage = computed(
    () => Object.values(form.errors)[0] as string | undefined,
);
const message = computed(() => {
    if (props.pricing === null) {
        return "Prices couldn't be loaded from Pixelforge. Reload the page to try again.";
    }

    const first = problems.value[0];

    if (first && (touched.value || first.field !== 'prompt')) {
        return first.message;
    }

    return first ? undefined : serverMessage.value;
});
const canGenerate = computed(
    () =>
        props.pricing !== null &&
        problems.value.length === 0 &&
        !form.processing,
);

const capitalise = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

function onPromptInput(): void {
    touched.value = true;
    form.clearErrors();
}

function pickSize(size: Size): void {
    settings.value.size = size;
    open.value.size = false;
}

function pickQuality(quality: Quality): void {
    settings.value.quality = quality;
    open.value.quality = false;
}

async function addReference(): Promise<void> {
    settings.value.referenceImages.push('');
    await nextTick();
    const inputs = document.querySelectorAll<HTMLInputElement>(
        '[data-reference-input]',
    );
    inputs[inputs.length - 1]?.focus();
}

function removeReference(index: number): void {
    settings.value.referenceImages.splice(index, 1);
}

/** "Try again" / "Use these settings": copy a generation's settings into the bar, for a fresh estimate. */
async function fill(copied: {
    prompt: string;
    size: Size;
    quality: Quality;
    reference_images: string[];
}): Promise<void> {
    settings.value = {
        prompt: copied.prompt,
        size: copied.size,
        quality: copied.quality,
        referenceImages: [...copied.reference_images],
    };
    touched.value = true;
    form.clearErrors();
    await nextTick();
    document.getElementById('prompt')?.focus();
}

defineExpose({ fill });

function generate(): void {
    touched.value = true;

    if (!canGenerate.value) {
        return;
    }

    form.prompt = settings.value.prompt;
    form.size = settings.value.size;
    form.quality = settings.value.quality;
    form.reference_images = references.value;
    form.post(store().url, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <form
        class="fixed bottom-[22px] left-1/2 z-30 w-[min(880px,calc(100%-32px))] -translate-x-1/2 rounded-xl border border-border bg-card shadow-[0_12px_40px_rgba(0,0,0,.05)]"
        aria-label="Prompt bar"
        @submit.prevent="generate"
    >
        <label for="prompt" class="sr-only">Prompt</label>
        <textarea
            id="prompt"
            v-model="settings.prompt"
            rows="2"
            placeholder="Describe an image…"
            class="block max-h-45 min-h-[58px] w-full resize-none border-0 bg-transparent px-[18px] pt-4 pb-1.5 font-serif text-[17px]/[1.45] text-foreground focus:outline-none"
            :aria-invalid="characters > MAX_PROMPT_CHARACTERS"
            :aria-describedby="message ? 'prompt-bar-message' : undefined"
            @input="onPromptInput"
            @keydown.enter.meta.prevent="generate"
            @keydown.enter.ctrl.prevent="generate"
        ></textarea>

        <p
            v-if="message"
            id="prompt-bar-message"
            class="m-0 px-[18px] pb-2.5 text-xs text-destructive"
            role="status"
        >
            {{ message }}
        </p>

        <div class="flex flex-wrap items-center gap-2 px-3 pt-2 pb-3">
            <Popover v-model:open="open.size">
                <PopoverTrigger class="chip">
                    {{ sizeOption.name }} <small>{{ sizeOption.ratio }}</small>
                </PopoverTrigger>
                <PopoverContent aria-label="Size">
                    <button
                        v-for="size in SIZES"
                        :key="size.value"
                        type="button"
                        class="option"
                        :aria-pressed="settings.size === size.value"
                        @click="pickSize(size.value)"
                    >
                        {{ size.name }}
                        <small>{{ size.ratio }} · {{ size.value }}</small>
                    </button>
                </PopoverContent>
            </Popover>

            <Popover v-model:open="open.quality">
                <PopoverTrigger class="chip">
                    {{ capitalise(settings.quality) }} quality
                </PopoverTrigger>
                <PopoverContent aria-label="Quality">
                    <button
                        v-for="quality in QUALITIES"
                        :key="quality"
                        type="button"
                        class="option"
                        :aria-pressed="settings.quality === quality"
                        @click="pickQuality(quality)"
                    >
                        {{ capitalise(quality) }}
                        <small v-if="pricing"
                            ><Amount
                                :nanodollars="estimateFor(quality)!.low"
                                rounding="down" />–<Amount
                                :nanodollars="estimateFor(quality)!.high"
                                rounding="up"
                        /></small>
                    </button>
                    <p class="m-0 px-2.5 pt-1 text-xs text-muted-foreground">
                        Higher quality draws more and thinks longer.
                    </p>
                </PopoverContent>
            </Popover>

            <Popover v-model:open="open.references">
                <PopoverTrigger
                    class="chip"
                    :class="{ bad: referencesHaveProblem }"
                >
                    References
                    <small
                        >{{ references.length }}/{{
                            MAX_REFERENCE_IMAGES
                        }}</small
                    >
                </PopoverTrigger>
                <PopoverContent class="w-[380px]" aria-label="Reference images">
                    <div class="mb-1.5 text-xs text-muted-foreground">
                        Reference image URLs · up to 4 ·
                        <template v-if="pricing"
                            ><Amount
                                :nanodollars="
                                    charge(
                                        pricing.tokens_per_reference_image *
                                            pricing.rates.input_image_tokens,
                                    )
                                "
                                rounding="up"
                            />
                            each</template
                        >
                    </div>
                    <template
                        v-for="(_, index) in settings.referenceImages"
                        :key="index"
                    >
                        <div class="flex items-center gap-1.5">
                            <label :for="`reference-${index}`" class="sr-only"
                                >Reference {{ index + 1 }}</label
                            >
                            <input
                                :id="`reference-${index}`"
                                v-model="settings.referenceImages[index]"
                                data-reference-input
                                type="url"
                                inputmode="url"
                                placeholder="https://"
                                class="mb-1 w-full border-0 border-b border-border bg-transparent py-1.5 font-mono text-xs focus:outline-none aria-invalid:border-destructive aria-invalid:text-destructive"
                                :aria-invalid="badReference(index)"
                                :aria-describedby="
                                    badReference(index)
                                        ? `reference-error-${index}`
                                        : undefined
                                "
                                @input="form.clearErrors()"
                            />
                            <button
                                type="button"
                                class="chip"
                                :aria-label="`Remove reference ${index + 1}`"
                                @click="removeReference(index)"
                            >
                                ×
                            </button>
                        </div>
                        <p
                            v-if="badReference(index)"
                            :id="`reference-error-${index}`"
                            class="m-0 mb-1.5 text-[11.5px] text-destructive"
                        >
                            Use a full link starting with https://
                        </p>
                    </template>
                    <button
                        v-if="
                            settings.referenceImages.length <
                            MAX_REFERENCE_IMAGES
                        "
                        type="button"
                        class="chip mt-1.5"
                        @click="addReference"
                    >
                        Add URL
                    </button>
                    <p v-else class="m-0 mt-1.5 text-xs text-muted-foreground">
                        That's the maximum of 4.
                    </p>
                </PopoverContent>
            </Popover>

            <span class="flex-1"></span>

            <span
                class="font-mono text-[11px] text-muted-foreground"
                :class="{
                    'font-semibold text-destructive':
                        characters > MAX_PROMPT_CHARACTERS,
                }"
                >{{ characters.toLocaleString('en-US') }}/4,000</span
            >

            <Popover v-if="current">
                <PopoverTrigger class="chip font-mono" aria-haspopup="dialog">
                    <template v-if="settings.prompt.trim()"
                        ><Amount
                            :nanodollars="current.low"
                            rounding="down" />–<Amount
                            :nanodollars="current.high"
                            rounding="up"
                    /></template>
                    <template v-else>Price</template>
                </PopoverTrigger>
                <PopoverContent
                    align="end"
                    class="w-[340px] max-[720px]:w-[calc(100vw-32px)]"
                    aria-label="Price breakdown"
                >
                    <EstimateReceipt
                        :estimate="current"
                        :prompt-characters="characters"
                        :reference-image-count="
                            Math.min(references.length, MAX_REFERENCE_IMAGES)
                        "
                        :quality="settings.quality"
                        :size="settings.size"
                    />
                </PopoverContent>
            </Popover>
            <span v-else class="chip font-mono" aria-disabled="true"
                >Price unavailable</span
            >

            <button
                type="submit"
                class="cursor-pointer rounded-sm border-0 bg-primary px-4 py-2.5 font-medium whitespace-nowrap text-primary-foreground hover:bg-[#333] active:scale-[.98] disabled:cursor-not-allowed disabled:bg-[#cfcdc8]"
                :disabled="!canGenerate"
                :title="canGenerate ? undefined : message"
            >
                Generate
            </button>
        </div>
    </form>
</template>
