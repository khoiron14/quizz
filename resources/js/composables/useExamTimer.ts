import { useIntervalFn } from '@vueuse/core';
import { computed, ref } from 'vue';

/**
 * Server-authoritative visual countdown timer for student exam.
 */
export function useExamTimer(
    deadlineIso: string,
    onExpired?: () => void,
) {
    const deadlineTime = new Date(deadlineIso).getTime();

    const calculateRemaining = (): number => {
        const diff = Math.floor((deadlineTime - Date.now()) / 1000);
        return Math.max(0, diff);
    };

    const remainingSeconds = ref<number>(calculateRemaining());
    const isExpired = computed(() => remainingSeconds.value <= 0);
    const isWarning = computed(() => remainingSeconds.value > 0 && remainingSeconds.value <= 300); // <= 5 minutes

    const formattedTime = computed<string>(() => {
        const total = remainingSeconds.value;
        const hours = Math.floor(total / 3600);
        const minutes = Math.floor((total % 3600) / 60);
        const seconds = total % 60;

        const pad = (n: number) => n.toString().padStart(2, '0');

        if (hours > 0) {
            return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
        }

        return `${pad(minutes)}:${pad(seconds)}`;
    });

    const { pause, resume } = useIntervalFn(() => {
        const next = calculateRemaining();
        remainingSeconds.value = next;

        if (next <= 0) {
            pause();
            if (onExpired) {
                onExpired();
            }
        }
    }, 1000);

    return {
        remainingSeconds,
        formattedTime,
        isWarning,
        isExpired,
        pause,
        resume,
    };
}

