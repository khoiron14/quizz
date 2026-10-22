<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Check,
    CheckCircle2,
    Clock,
    Cloud,
    Flag,
    Loader2,
    Send,
    WifiOff,
} from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { AnswerItem, QuestionItem, useCbtEngine } from '@/composables/useCbtEngine';
import { useExamTimer } from '@/composables/useExamTimer';

interface Props {
    attempt: {
        id: string;
        exam_id: string;
        started_at: string;
        extra_time_minutes: number;
        deadline: string;
    };
    exam: {
        id: string;
        title: string;
        duration_minutes: number;
        is_randomize: boolean;
    };
    questions: QuestionItem[];
    initialAnswers: Record<string, AnswerItem>;
}

const props = defineProps<Props>();

// Destructure CBT Engine so refs and computed properties unwrap naturally in the template
const {
    answers,
    syncState,
    isOnline,
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
} = useCbtEngine(props.attempt.id, props.questions, props.initialAnswers);

// Submission Dialog State
const showSubmitDialog = ref<boolean>(false);
const isSubmitting = ref<boolean>(false);

const executeSubmission = () => {
    if (isSubmitting.value) {
        return;
    }
    isSubmitting.value = true;

    router.post(
        `/exam/${props.attempt.id}/submit`,
        {},
        {
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

// Initialize visual countdown timer (Server-authoritative deadline)
const timer = useExamTimer(props.attempt.deadline, () => {
    // Automatically submit when timer runs out
    executeSubmission();
});
</script>

<template>
    <Head :title="`Ujian: ${exam.title}`" />

    <div class="flex min-h-screen flex-col bg-background text-foreground select-none">
        <!-- CBT Fixed Topbar -->
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b bg-card px-4 sm:px-6 shadow-xs">
            <div class="flex items-center gap-3">
                <div class="h-8 w-1 rounded-full bg-primary"></div>
                <div>
                    <h1 class="text-sm sm:text-base font-bold line-clamp-1">
                        {{ exam.title }}
                    </h1>
                    <p class="text-xs text-muted-foreground hidden sm:block">
                        Durasi: {{ exam.duration_minutes }} Menit &bull; {{ totalQuestions }} Soal
                    </p>
                </div>
            </div>

            <!-- Central Indicators: Timer & Sync Status -->
            <div class="flex items-center gap-3 sm:gap-6">
                <!-- Sync Status Badge -->
                <div class="flex items-center gap-1.5 text-xs font-medium">
                    <template v-if="!isOnline">
                        <Badge variant="destructive" class="flex items-center gap-1">
                            <WifiOff class="h-3 w-3" />
                            <span class="hidden md:inline">Offline (Disimpan di Lokal)</span>
                        </Badge>
                    </template>
                    <template v-else-if="syncState === 'saving'">
                        <Badge variant="secondary" class="flex items-center gap-1 bg-amber-500/10 text-amber-600 dark:text-amber-400">
                            <Loader2 class="h-3 w-3 animate-spin" />
                            <span class="hidden md:inline">Menyinkronkan...</span>
                        </Badge>
                    </template>
                    <template v-else>
                        <Badge variant="secondary" class="flex items-center gap-1 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <Cloud class="h-3 w-3" />
                            <span class="hidden md:inline">Tersimpan</span>
                        </Badge>
                    </template>
                </div>

                <!-- Visual Server-Synced Timer -->
                <div
                    class="flex items-center gap-2 rounded-lg border px-3 py-1.5 font-mono text-sm sm:text-base font-bold shadow-xs transition-colors"
                    :class="timer.isWarning ? 'border-destructive bg-destructive/10 text-destructive animate-pulse' : 'bg-muted/50 text-foreground'"
                >
                    <Clock class="h-4 w-4" />
                    <span>{{ timer.formattedTime }}</span>
                </div>

                <!-- Submit Button -->
                <Button
                    variant="default"
                    size="sm"
                    class="font-medium bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5"
                    @click="showSubmitDialog = true"
                >
                    <Send class="h-3.5 w-3.5" />
                    <span class="hidden sm:inline">Kumpulkan</span>
                </Button>
            </div>
        </header>

        <!-- Main Workspace: Question Area (Left) + Navigator Grid (Right) -->
        <div class="flex-1 container mx-auto p-4 sm:p-6 lg:p-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Question Column (8 Cols) -->
                <main class="lg:col-span-8 space-y-4">
                    <Card class="border shadow-xs">
                        <!-- Question Header -->
                        <CardHeader class="pb-3 border-b flex flex-row items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Badge variant="outline" class="font-bold text-xs uppercase px-2.5 py-1">
                                    Soal Nomor {{ currentIndex + 1 }}
                                </Badge>
                                <Badge variant="secondary" class="text-xs">
                                    {{ currentQuestion.score_weight }} Poin
                                </Badge>
                            </div>

                            <!-- Flag / Ragu-ragu Toggle -->
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5 text-xs transition-colors"
                                :class="currentAnswer.is_flagged ? 'border-amber-500 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' : 'text-muted-foreground'"
                                @click="toggleFlag"
                            >
                                <Flag class="h-3.5 w-3.5" :class="{ 'fill-amber-500 text-amber-500': currentAnswer.is_flagged }" />
                                <span>Ragu-ragu</span>
                            </Button>
                        </CardHeader>

                        <!-- Question Body -->
                        <CardContent class="pt-6 pb-6 space-y-6">
                            <!-- Question Text -->
                            <div class="text-base sm:text-lg font-medium leading-relaxed whitespace-pre-wrap text-foreground select-text">
                                {{ currentQuestion.question_text }}
                            </div>

                            <!-- Multiple Choice Options -->
                            <div v-if="currentQuestion.type === 'multiple_choice'" class="space-y-3 pt-2">
                                <button
                                    v-for="opt in currentQuestion.options ?? []"
                                    :key="opt.key"
                                    type="button"
                                    class="w-full text-left flex items-start gap-4 p-4 rounded-xl border transition-all cursor-pointer group text-sm sm:text-base"
                                    :class="currentAnswer.answer_content === opt.key
                                        ? 'border-primary bg-primary/5 ring-2 ring-primary/20 shadow-xs'
                                        : 'border-border/80 hover:border-primary/50 hover:bg-muted/40'"
                                    @click="selectMultipleChoice(opt.key)"
                                >
                                    <!-- Option Key Badge -->
                                    <span
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg font-bold text-sm border transition-colors"
                                        :class="currentAnswer.answer_content === opt.key
                                            ? 'bg-primary text-primary-foreground border-primary'
                                            : 'bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary border-border'"
                                    >
                                        {{ opt.key }}
                                    </span>

                                    <!-- Option Text -->
                                    <span class="flex-1 pt-1 leading-normal select-text">
                                        {{ opt.text }}
                                    </span>
                                </button>
                            </div>

                            <!-- Essay Textarea -->
                            <div v-else-if="currentQuestion.type === 'essay'" class="space-y-2 pt-2">
                                <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Tuliskan Jawaban Esai Anda:
                                </label>
                                <textarea
                                    :value="currentAnswer.answer_content ?? ''"
                                    rows="8"
                                    placeholder="Ketik jawaban lengkap Anda di sini..."
                                    class="w-full rounded-xl border border-input bg-background p-4 text-sm sm:text-base leading-relaxed focus:outline-hidden focus:ring-2 focus:ring-primary focus:border-primary resize-y select-text"
                                    @input="(e) => updateEssayContent((e.target as HTMLTextAreaElement).value)"
                                ></textarea>
                                <p class="text-xs text-muted-foreground text-right">
                                    Otomatis tersimpan di memori perangkat tiap ketukan tombol.
                                </p>
                            </div>
                        </CardContent>

                        <!-- Question Navigation Bar -->
                        <CardFooter class="border-t bg-muted/20 px-6 py-4 flex items-center justify-between">
                            <Button
                                variant="outline"
                                :disabled="currentIndex === 0"
                                @click="previousQuestion"
                            >
                                <ArrowLeft class="h-4 w-4 mr-2" />
                                Sebelumnya
                            </Button>

                            <div class="text-xs text-muted-foreground hidden sm:block">
                                Navigasi instan &bull; 0ms latensi server
                            </div>

                            <Button
                                v-if="currentIndex < totalQuestions - 1"
                                variant="default"
                                @click="nextQuestion"
                            >
                                Berikutnya
                                <ArrowRight class="h-4 w-4 ml-2" />
                            </Button>
                            <Button
                                v-else
                                variant="default"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white"
                                @click="showSubmitDialog = true"
                            >
                                Selesai Ujian
                                <Check class="h-4 w-4 ml-2" />
                            </Button>
                        </CardFooter>
                    </Card>
                </main>

                <!-- Number Navigator Column (4 Cols) -->
                <aside class="lg:col-span-4 space-y-4">
                    <Card class="border shadow-xs">
                        <CardHeader class="pb-3 border-b">
                            <div class="flex items-center justify-between">
                                <h2 class="font-bold text-sm tracking-wide">
                                    Nomor Soal
                                </h2>
                                <span class="text-xs font-medium text-muted-foreground">
                                    {{ answeredCount }} / {{ totalQuestions }} Terjawab
                                </span>
                            </div>
                        </CardHeader>

                        <CardContent class="pt-4 space-y-4">
                            <!-- Question Buttons Grid -->
                            <div class="grid grid-cols-5 gap-2">
                                <button
                                    v-for="(q, idx) in questions"
                                    :key="q.id"
                                    type="button"
                                    class="relative flex h-11 w-full items-center justify-center rounded-lg font-bold text-sm transition-all cursor-pointer border"
                                    :class="[
                                        idx === currentIndex
                                            ? 'ring-2 ring-primary ring-offset-2'
                                            : '',
                                        answers[q.id]?.is_flagged
                                            ? 'bg-amber-500 text-white border-amber-600 hover:bg-amber-600'
                                            : answers[q.id]?.answer_content && answers[q.id]?.answer_content?.trim() !== ''
                                            ? 'bg-emerald-600 text-white border-emerald-700 hover:bg-emerald-700'
                                            : 'bg-muted/40 text-foreground border-border hover:bg-muted'
                                    ]"
                                    @click="goToQuestion(idx)"
                                >
                                    {{ idx + 1 }}
                                    <span
                                        v-if="answers[q.id]?.is_flagged"
                                        class="absolute top-1 right-1 h-1.5 w-1.5 rounded-full bg-white"
                                    ></span>
                                </button>
                            </div>

                            <!-- Legend -->
                            <div class="pt-2 border-t grid grid-cols-3 gap-2 text-xs text-muted-foreground text-center">
                                <div class="flex items-center gap-1.5 justify-center">
                                    <span class="h-3 w-3 rounded-sm bg-emerald-600 shrink-0"></span>
                                    <span>Sudah ({{ answeredCount }})</span>
                                </div>
                                <div class="flex items-center gap-1.5 justify-center">
                                    <span class="h-3 w-3 rounded-sm bg-amber-500 shrink-0"></span>
                                    <span>Ragu ({{ flaggedCount }})</span>
                                </div>
                                <div class="flex items-center gap-1.5 justify-center">
                                    <span class="h-3 w-3 rounded-sm bg-muted border shrink-0"></span>
                                    <span>Belum ({{ unansweredCount }})</span>
                                </div>
                            </div>
                        </CardContent>

                        <CardFooter class="pt-0">
                            <Button
                                variant="outline"
                                class="w-full text-xs font-semibold text-emerald-600 border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50 dark:hover:bg-emerald-950/30"
                                @click="showSubmitDialog = true"
                            >
                                Kumpulkan Jawaban Sekarang
                            </Button>
                        </CardFooter>
                    </Card>
                </aside>
            </div>
        </div>

        <!-- Submission Confirmation Modal Dialog -->
        <Dialog :open="showSubmitDialog" @update:open="showSubmitDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-lg">
                        <AlertTriangle v-if="unansweredCount > 0" class="h-5 w-5 text-amber-500" />
                        <CheckCircle2 v-else class="h-5 w-5 text-emerald-500" />
                        Konfirmasi Kumpulkan Ujian
                    </DialogTitle>
                    <DialogDescription>
                        Periksa kembali status ringkasan pengerjaan Anda sebelum mengumpulkan.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3 py-3">
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-xl border bg-card p-3">
                            <div class="text-2xl font-bold text-emerald-600">{{ answeredCount }}</div>
                            <div class="text-xs text-muted-foreground mt-1">Terjawab</div>
                        </div>
                        <div class="rounded-xl border bg-card p-3">
                            <div class="text-2xl font-bold text-amber-500">{{ flaggedCount }}</div>
                            <div class="text-xs text-muted-foreground mt-1">Ragu-ragu</div>
                        </div>
                        <div class="rounded-xl border bg-card p-3" :class="{ 'border-destructive/50 bg-destructive/5': unansweredCount > 0 }">
                            <div class="text-2xl font-bold" :class="unansweredCount > 0 ? 'text-destructive' : 'text-muted-foreground'">
                                {{ unansweredCount }}
                            </div>
                            <div class="text-xs text-muted-foreground mt-1">Kosong</div>
                        </div>
                    </div>

                    <div v-if="unansweredCount > 0" class="rounded-lg border border-destructive/20 bg-destructive/10 p-3 text-xs text-destructive">
                        Masih terdapat <strong>{{ unansweredCount }} soal</strong> yang belum Anda jawab. Jawaban kosong akan dinilai 0 poin.
                    </div>
                    <div v-else-if="flaggedCount > 0" class="rounded-lg border border-amber-500/20 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-300">
                        Masih ada <strong>{{ flaggedCount }} soal</strong> bertanda ragu-ragu. Pilihan Anda tetap akan diperhitungkan.
                    </div>
                </div>

                <DialogFooter class="flex sm:justify-between gap-2">
                    <Button variant="outline" :disabled="isSubmitting" @click="showSubmitDialog = false">
                        Lanjut Mengerjakan
                    </Button>
                    <Button
                        variant="default"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium"
                        :disabled="isSubmitting"
                        @click="executeSubmission"
                    >
                        <Loader2 v-if="isSubmitting" class="h-4 w-4 mr-2 animate-spin" />
                        <span v-if="isSubmitting">Mengumpulkan...</span>
                        <span v-else>Ya, Kumpulkan Ujian</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
