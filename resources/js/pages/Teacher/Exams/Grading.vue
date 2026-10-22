<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Award,
    Check,
    CheckCircle2,
    FileCheck2,
    FileText,
    HelpCircle,
    Save,
    UserCheck,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

interface QuestionData {
    id: string;
    type: string;
    question_text: string;
    score_weight: number;
}

interface StudentAnswerData {
    id: string;
    question_id: string;
    answer_content: string | null;
    score_awarded: number | null;
    question?: QuestionData;
}

interface AttemptData {
    id: string;
    student_id: string;
    student: {
        id: string;
        name: string;
        email: string;
    };
    status: string;
    total_score: number | null;
    answers: StudentAnswerData[];
}

interface ExamData {
    id: string;
    title: string;
    questions?: QuestionData[];
}

const props = defineProps<{
    exam: ExamData;
    attempts: AttemptData[];
}>();

// Map essay answers across all attempts
interface EssayItem {
    answerId: string;
    attemptId: string;
    studentName: string;
    studentEmail: string;
    questionId: string;
    questionText: string;
    scoreWeight: number;
    answerContent: string | null;
    scoreAwarded: number | null;
}

const essayItems = computed<EssayItem[]>(() => {
    const list: EssayItem[] = [];

    props.attempts.forEach((att) => {
        att.answers?.forEach((ans) => {
            const q = ans.question ?? props.exam.questions?.find((eq) => eq.id === ans.question_id);
            if (q && q.type === 'essay') {
                list.push({
                    answerId: ans.id,
                    attemptId: att.id,
                    studentName: att.student.name,
                    studentEmail: att.student.email,
                    questionId: q.id,
                    questionText: q.question_text,
                    scoreWeight: q.score_weight,
                    answerContent: ans.answer_content,
                    scoreAwarded: ans.score_awarded,
                });
            }
        });
    });

    return list;
});

// Scoring state for each answer
const scores = ref<Record<string, number>>({});
const isSaving = ref<Record<string, boolean>>({});
const saveSuccess = ref<Record<string, boolean>>({});

// Initialize score inputs
essayItems.value.forEach((item) => {
    scores.value[item.answerId] = item.scoreAwarded ?? 0;
});

const saveScore = (item: EssayItem) => {
    const scoreVal = scores.value[item.answerId] ?? 0;
    isSaving.value[item.answerId] = true;

    router.post(
        `/teacher/answers/${item.answerId}/grade`,
        { score_awarded: scoreVal },
        {
            preserveScroll: true,
            onSuccess: () => {
                saveSuccess.value[item.answerId] = true;
                setTimeout(() => {
                    saveSuccess.value[item.answerId] = false;
                }, 2000);
            },
            onFinish: () => {
                isSaving.value[item.answerId] = false;
            },
        },
    );
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Kelola Ujian', href: '/teacher/exams' },
            { title: exam.title, href: `/teacher/exams/${exam.id}` },
            { title: 'Penilaian Esai', href: `/teacher/exams/${exam.id}/grading` },
        ]"
    >
        <Head :title="`Penilaian Esai: ${exam.title}`" />

        <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <Button as-child variant="ghost" size="sm" class="mb-2 text-muted-foreground">
                        <Link :href="`/teacher/exams/${exam.id}`">
                            <ArrowLeft class="h-4 w-4 mr-1.5" />
                            Kembali ke Detail Ujian
                        </Link>
                    </Button>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Koreksi & Penilaian Jawaban Esai
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Periksa jawaban teks uraian peserta dan masukkan bobot nilai yang diperoleh.
                    </p>
                </div>
            </div>

            <!-- Empty State -->
            <Card v-if="essayItems.length === 0" class="border-dashed py-12 text-center">
                <CardContent class="space-y-4">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <FileText class="h-6 w-6" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="font-semibold text-foreground">Tidak Ada Jawaban Esai</h3>
                        <p class="text-xs text-muted-foreground max-w-sm mx-auto">
                            Ujian ini belum memiliki soal esai atau belum ada siswa yang mengirimkan jawaban esai.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Essay Grading List -->
            <div v-else class="space-y-6">
                <Card
                    v-for="item in essayItems"
                    :key="item.answerId"
                    class="border shadow-xs"
                >
                    <CardHeader class="pb-3 border-b bg-muted/20">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <CardTitle class="text-base font-bold text-foreground">
                                    {{ item.studentName }}
                                </CardTitle>
                                <CardDescription class="text-xs">
                                    {{ item.studentEmail }}
                                </CardDescription>
                            </div>

                            <Badge variant="outline" class="font-bold text-xs uppercase px-2 py-1">
                                Bobot Maksimal: {{ item.scoreWeight }} Poin
                            </Badge>
                        </div>
                    </CardHeader>

                    <CardContent class="pt-5 pb-5 space-y-4">
                        <!-- Question Prompt -->
                        <div class="rounded-lg border bg-muted/30 p-3 text-sm">
                            <span class="text-xs font-bold text-muted-foreground block mb-1">Pertanyaan:</span>
                            <div class="font-medium text-foreground whitespace-pre-wrap">{{ item.questionText }}</div>
                        </div>

                        <!-- Student's Answer -->
                        <div class="space-y-1.5">
                            <span class="text-xs font-bold text-muted-foreground">Jawaban Siswa:</span>
                            <div
                                v-if="item.answerContent && item.answerContent.trim() !== ''"
                                class="rounded-xl border border-border bg-card p-4 text-sm leading-relaxed whitespace-pre-wrap select-text font-mono"
                            >
                                {{ item.answerContent }}
                            </div>
                            <div v-else class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground italic">
                                Siswa tidak menuliskan jawaban untuk pertanyaan ini (kosong).
                            </div>
                        </div>

                        <!-- Score Input and Submit -->
                        <div class="pt-3 border-t flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <Label for="score_input" class="text-sm font-semibold">Skor Nilai Diberikan:</Label>
                                <div class="flex items-center gap-2">
                                    <Input
                                        id="score_input"
                                        v-model="scores[item.answerId]"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        :max="item.scoreWeight"
                                        class="w-24 font-bold text-base text-center"
                                    />
                                    <span class="text-xs text-muted-foreground">/ {{ item.scoreWeight }}</span>
                                </div>
                            </div>

                            <Button
                                size="sm"
                                class="gap-1.5"
                                :disabled="isSaving[item.answerId]"
                                @click="saveScore(item)"
                            >
                                <Check v-if="saveSuccess[item.answerId]" class="h-4 w-4 text-emerald-400" />
                                <Save v-else class="h-4 w-4" />
                                <span v-if="isSaving[item.answerId]">Menyimpan...</span>
                                <span v-else-if="saveSuccess[item.answerId]">Tersimpan!</span>
                                <span v-else>Simpan Nilai</span>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Final Score Recap Table -->
                <Card class="border shadow-xs mt-8">
                    <CardHeader class="pb-3 border-b">
                        <CardTitle class="text-base font-bold flex items-center gap-2">
                            <Award class="h-5 w-5 text-primary" />
                            Rekapitulasi Nilai Akhir Peserta
                        </CardTitle>
                    </CardHeader>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-muted/50 border-b text-xs font-bold text-muted-foreground uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3">Nama Siswa</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Total Skor Akhir</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="att in attempts" :key="att.id" class="hover:bg-muted/30">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-foreground">{{ att.student.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ att.student.email }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge variant="outline" class="capitalize text-xs font-semibold">
                                            {{ att.status.replace('_', ' ') }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 font-bold text-base text-primary">
                                        {{ att.total_score !== null ? att.total_score : '-' }} Poin
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
