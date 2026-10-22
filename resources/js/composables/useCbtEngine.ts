import { useDebounceFn, useNetwork, useStorage } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

export type SyncState = 'synced' | 'saving' | 'offline' | 'error';

export interface AnswerItem {
    answer_content: string | null;
    is_flagged: boolean;
}

export interface QuestionItem {
    id: string;
    type: 'multiple_choice' | 'essay';
    question_text: string;
    options?: Array<{ key: string; text: string }> | null;
    score_weight: number;
    order: number;
}

/**
 * Robust, local-first CBT Exam client engine.
 * Guarantees zero data loss across Wi-Fi drops, tab reloads, and browser crashes.
 */
export function useCbtEngine(
    attemptId: string,
    questions: QuestionItem[],
    initialAnswers: Record<string, AnswerItem> = {},
) {
    // 1. Reactive Storage (Local-First Persistence via useStorage)
    const answers = useStorage<Record<string, AnswerItem>>(
        `cbt_attempt_${attemptId}_answers`,
        { ...initialAnswers },
    );

    // Ensure all questions have a slot in local state
    questions.forEach((q) => {
        if (!answers.value[q.id]) {
            answers.value[q.id] = initialAnswers[q.id] ?? {
                answer_content: null,
                is_flagged: false,
            };
        }
    });

    // 2. Offline Sync Queue
    const syncQueue = useStorage<string[]>(`cbt_attempt_${attemptId}_queue`, []);

    // 3. Network Monitoring
    const { isOnline } = useNetwork();
    const syncState = ref<SyncState>('synced');
    const lastSyncedAt = ref<string>(new Date().toISOString());

    // 4. In-Memory Question Navigation (0ms Latency)
    const currentIndex = ref<number>(0);

    const currentQuestion = computed<QuestionItem>(() => {
        return questions[currentIndex.value] ?? questions[0];
    });

    const totalQuestions = computed(() => questions.length);

    const currentAnswer = computed<AnswerItem>({
        get() {
            const qId = currentQuestion.value?.id;
            return (
                answers.value[qId] ?? { answer_content: null, is_flagged: false }
            );
        },
        set(val) {
            const qId = currentQuestion.value?.id;
            if (qId) {
                answers.value[qId] = val;
            }
        },
    });

    // 5. Background API Sync Worker
    const isSyncing = ref<boolean>(false);

    const processSyncQueue = async () => {
        if (!isOnline.value || isSyncing.value || syncQueue.value.length === 0) {
            return;
        }

        isSyncing.value = true;
        syncState.value = 'saving';

        while (syncQueue.value.length > 0 && isOnline.value) {
            const questionId = syncQueue.value[0];
            const answerPayload = answers.value[questionId];

            if (!answerPayload) {
                syncQueue.value.shift();
                continue;
            }

            try {
                // Get CSRF token from document cookie or meta tag
                const tokenMeta = document.querySelector(
                    'meta[name="csrf-token"]',
                );
                const csrfToken = tokenMeta
                    ? tokenMeta.getAttribute('content')
                    : '';

                const response = await fetch(
                    `/api/exams/${attemptId}/answers`,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken ?? '',
                        },
                        body: JSON.stringify({
                            question_id: questionId,
                            answer_content: answerPayload.answer_content,
                            is_flagged: answerPayload.is_flagged,
                        }),
                    },
                );

                if (response.ok) {
                    const data = await response.json();
                    lastSyncedAt.value = data.last_synced_at ?? new Date().toISOString();
                    // Remove processed item from queue
                    syncQueue.value.shift();
                } else if (response.status === 403 || response.status === 401) {
                    // Session expired or attempt completed
                    syncState.value = 'error';
                    break;
                } else {
                    // Server error, keep in queue and retry
                    syncState.value = isOnline.value ? 'error' : 'offline';
                    break;
                }
            } catch (err) {
                // Network failure or fetch timeout
                syncState.value = 'offline';
                break;
            }
        }

        isSyncing.value = false;

        if (syncQueue.value.length === 0) {
            syncState.value = isOnline.value ? 'synced' : 'offline';
        }
    };

    // Watch online status to flush queue immediately when connection is restored
    watch(isOnline, (online) => {
        if (online) {
            processSyncQueue();
        } else {
            syncState.value = 'offline';
        }
    });

    const enqueueSync = (questionId: string) => {
        if (!syncQueue.value.includes(questionId)) {
            syncQueue.value.push(questionId);
        }
        processSyncQueue();
    };

    // Debounced sync worker for essay typing (1.5s idle)
    const debouncedEnqueueSync = useDebounceFn((questionId: string) => {
        enqueueSync(questionId);
    }, 1500);

    // 6. Action Handlers
    const selectMultipleChoice = (key: string) => {
        const qId = currentQuestion.value.id;
        const current = answers.value[qId] ?? {
            answer_content: null,
            is_flagged: false,
        };

        // 0ms instant local commit
        answers.value[qId] = {
            ...current,
            answer_content: key,
        };

        // Immediate background sync
        enqueueSync(qId);
    };

    const updateEssayContent = (content: string) => {
        const qId = currentQuestion.value.id;
        const current = answers.value[qId] ?? {
            answer_content: null,
            is_flagged: false,
        };

        // 0ms keystroke local commit
        answers.value[qId] = {
            ...current,
            answer_content: content,
        };

        // Debounced sync to prevent API saturation
        syncState.value = 'saving';
        debouncedEnqueueSync(qId);
    };

    const toggleFlag = () => {
        const qId = currentQuestion.value.id;
        const current = answers.value[qId] ?? {
            answer_content: null,
            is_flagged: false,
        };

        answers.value[qId] = {
            ...current,
            is_flagged: !current.is_flagged,
        };

        enqueueSync(qId);
    };

    // 7. Question Navigation Helpers
    const goToQuestion = (index: number) => {
        if (index >= 0 && index < questions.length) {
            currentIndex.value = index;
        }
    };

    const nextQuestion = () => {
        if (currentIndex.value < questions.length - 1) {
            currentIndex.value++;
        }
    };

    const previousQuestion = () => {
        if (currentIndex.value > 0) {
            currentIndex.value--;
        }
    };

    // 8. Progress Metrics
    const answeredCount = computed(() => {
        return Object.values(answers.value).filter(
            (a) => a.answer_content !== null && a.answer_content.trim() !== '',
        ).length;
    });

    const flaggedCount = computed(() => {
        return Object.values(answers.value).filter((a) => a.is_flagged).length;
    });

    const unansweredCount = computed(() => {
        return totalQuestions.value - answeredCount.value;
    });

    return {
        answers,
        syncQueue,
        syncState,
        isOnline,
        lastSyncedAt,
        currentIndex,
        currentQuestion,
        totalQuestions,
        currentAnswer,
        answeredCount,
        flaggedCount,
        unansweredCount,
        selectMultipleChoice,
        updateEssayContent,
        toggleFlag,
        goToQuestion,
        nextQuestion,
        previousQuestion,
        processSyncQueue,
    };
}
