<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    ArrowLeft,
    Check,
    CheckCircle2,
    Copy,
    Edit3,
    FileText,
    HelpCircle,
    ListOrdered,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

interface OptionItem {
    key: string;
    text: string;
}

interface QuestionItem {
    id: string;
    exam_id: string;
    type: 'multiple_choice' | 'essay';
    question_text: string;
    options: OptionItem[] | null;
    answer_key: string | { key: string } | null;
    score_weight: number;
    order: number;
}

interface ExamData {
    id: string;
    title: string;
    token: string;
    duration_minutes: number;
    start_time: string;
    end_time: string;
    is_randomize: boolean;
    status: 'draft' | 'published' | 'archived';
}

const props = defineProps<{
    exam: ExamData;
    questions: QuestionItem[];
}>();

// Token copying
const copiedToken = ref<boolean>(false);
const copyToken = () => {
    navigator.clipboard.writeText(props.exam.token);
    copiedToken.value = true;
    setTimeout(() => {
        copiedToken.value = false;
    }, 2000);
};

// Add Question Modal State
const showAddQuestionModal = ref<boolean>(false);

const questionForm = useForm({
    type: 'multiple_choice' as 'multiple_choice' | 'essay',
    question_text: '',
    options: [
        { key: 'A', text: '' },
        { key: 'B', text: '' },
        { key: 'C', text: '' },
        { key: 'D', text: '' },
    ] as OptionItem[],
    answer_key: 'A',
    score_weight: 1.0,
});

const setQuestionType = (type: 'multiple_choice' | 'essay') => {
    questionForm.type = type;
    if (type === 'essay') {
        questionForm.score_weight = 5.0;
        questionForm.options = null as any;
        questionForm.answer_key = null as any;
    } else {
        questionForm.score_weight = 1.0;
        questionForm.options = [
            { key: 'A', text: '' },
            { key: 'B', text: '' },
            { key: 'C', text: '' },
            { key: 'D', text: '' },
        ];
        questionForm.answer_key = 'A';
    }
};

const addOptionE = () => {
    if (!questionForm.options) return;
    if (questionForm.options.length === 4) {
        questionForm.options.push({ key: 'E', text: '' });
    }
};

const removeOptionE = () => {
    if (!questionForm.options) return;
    if (questionForm.options.length > 4) {
        questionForm.options.pop();
        if (questionForm.answer_key === 'E') {
            questionForm.answer_key = 'A';
        }
    }
};

const submitQuestion = () => {
    questionForm.post(`/teacher/exams/${props.exam.id}/questions`, {
        onSuccess: () => {
            showAddQuestionModal.value = false;
            questionForm.reset();
            setQuestionType('multiple_choice');
        },
    });
};

// Delete question modal
const questionToDelete = ref<QuestionItem | null>(null);
const isDeletingQuestion = ref<boolean>(false);

const confirmDeleteQuestion = (q: QuestionItem) => {
    questionToDelete.value = q;
};

const executeDeleteQuestion = () => {
    if (!questionToDelete.value) return;
    isDeletingQuestion.value = true;
    router.delete(`/teacher/exams/${props.exam.id}/questions/${questionToDelete.value.id}`, {
        onFinish: () => {
            isDeletingQuestion.value = false;
            questionToDelete.value = null;
        },
    });
};

const getCorrectAnswerKey = (answerKey: any): string => {
    if (!answerKey) return '-';
    if (typeof answerKey === 'object' && answerKey.key) {
        return answerKey.key;
    }
    return String(answerKey);
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Kelola Ujian', href: '/teacher/exams' },
            { title: exam.title, href: `/teacher/exams/${exam.id}` },
        ]"
    >
        <Head :title="`Soal: ${exam.title}`" />

        <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
            <!-- Top Action Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <Button as-child variant="ghost" size="sm" class="mb-2 text-muted-foreground">
                        <Link href="/teacher/exams">
                            <ArrowLeft class="h-4 w-4 mr-1.5" />
                            Kembali ke Daftar Ujian
                        </Link>
                    </Button>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                            {{ exam.title }}
                        </h1>
                        <Badge
                            :variant="exam.status === 'published' ? 'default' : 'secondary'"
                            class="capitalize text-xs font-semibold"
                            :class="exam.status === 'published' ? 'bg-emerald-600 text-white' : ''"
                        >
                            {{ exam.status }}
                        </Badge>
                    </div>
                </div>

                <!-- Action Navigation -->
                <div class="flex items-center gap-2">
                    <Button as-child variant="outline" class="gap-1.5 text-primary border-primary/30">
                        <Link :href="`/teacher/exams/${exam.id}/monitoring`">
                            <Activity class="h-4 w-4" />
                            Live Monitoring
                        </Link>
                    </Button>
                    <Button as-child variant="outline" class="gap-1.5">
                        <Link :href="`/teacher/exams/${exam.id}/grading`">
                            <FileText class="h-4 w-4" />
                            Nilai Esai
                        </Link>
                    </Button>
                    <Button class="gap-1.5 shadow-xs" @click="showAddQuestionModal = true">
                        <Plus class="h-4 w-4" />
                        Tambah Soal
                    </Button>
                </div>
            </div>

            <!-- Exam Info Card -->
            <Card class="border bg-muted/20">
                <CardContent class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                    <div>
                        <span class="text-muted-foreground block mb-0.5">Token Ujian:</span>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 font-mono text-sm font-bold bg-background px-2 py-0.5 rounded-md border"
                            @click="copyToken"
                        >
                            <span>{{ exam.token }}</span>
                            <Check v-if="copiedToken" class="h-3.5 w-3.5 text-emerald-600" />
                            <Copy v-else class="h-3.5 w-3.5 text-muted-foreground" />
                        </button>
                    </div>
                    <div>
                        <span class="text-muted-foreground block mb-0.5">Durasi Waktu:</span>
                        <span class="font-bold text-foreground text-sm">{{ exam.duration_minutes }} Menit</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground block mb-0.5">Total Soal:</span>
                        <span class="font-bold text-foreground text-sm">{{ questions.length }} Butir Soal</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground block mb-0.5">Acak Soal:</span>
                        <span class="font-semibold text-foreground text-sm">{{ exam.is_randomize ? 'Ya (Aktif)' : 'Tidak (Urut)' }}</span>
                    </div>
                </CardContent>
            </Card>

            <!-- Questions List Section -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-foreground flex items-center gap-2">
                        <ListOrdered class="h-5 w-5 text-primary" />
                        Daftar Butir Soal
                    </h2>
                </div>

                <!-- Empty Questions State -->
                <Card v-if="questions.length === 0" class="border-dashed py-12 text-center">
                    <CardContent class="space-y-4">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <HelpCircle class="h-6 w-6" />
                        </div>
                        <div class="space-y-1">
                            <h3 class="font-semibold text-foreground">Belum Ada Soal</h3>
                            <p class="text-xs text-muted-foreground max-w-sm mx-auto">
                                Ujian ini belum memiliki butir soal. Klik tombol di bawah untuk menambahkan soal pilihan ganda atau esai.
                            </p>
                        </div>
                        <Button size="sm" class="gap-1.5" @click="showAddQuestionModal = true">
                            <Plus class="h-4 w-4" />
                            Tambah Soal Pertama
                        </Button>
                    </CardContent>
                </Card>

                <!-- Questions List -->
                <div v-else class="space-y-4">
                    <Card
                        v-for="(q, idx) in questions"
                        :key="q.id"
                        class="border shadow-xs"
                    >
                        <CardHeader class="pb-3 border-b flex flex-row items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Badge variant="outline" class="font-bold text-xs uppercase">
                                    Soal #{{ idx + 1 }}
                                </Badge>
                                <Badge
                                    :variant="q.type === 'multiple_choice' ? 'secondary' : 'default'"
                                    class="text-xs font-semibold"
                                >
                                    {{ q.type === 'multiple_choice' ? 'Pilihan Ganda' : 'Esai' }}
                                </Badge>
                                <Badge variant="outline" class="text-xs">
                                    Bobot: {{ q.score_weight }} Poin
                                </Badge>
                            </div>

                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8 text-destructive/70 hover:text-destructive hover:bg-destructive/10"
                                @click="confirmDeleteQuestion(q)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </CardHeader>

                        <CardContent class="pt-4 pb-4 space-y-4">
                            <!-- Question Text -->
                            <p class="text-base font-medium leading-relaxed whitespace-pre-wrap">
                                {{ q.question_text }}
                            </p>

                            <!-- Multiple Choice Options Breakdown -->
                            <div v-if="q.type === 'multiple_choice' && q.options" class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2">
                                <div
                                    v-for="opt in q.options"
                                    :key="opt.key"
                                    class="flex items-center gap-3 p-3 rounded-lg border text-sm"
                                    :class="getCorrectAnswerKey(q.answer_key) === opt.key
                                        ? 'border-emerald-500 bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200 font-semibold'
                                        : 'border-border bg-card'"
                                >
                                    <span
                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md font-bold text-xs border"
                                        :class="getCorrectAnswerKey(q.answer_key) === opt.key
                                            ? 'bg-emerald-600 text-white border-emerald-600'
                                            : 'bg-muted text-muted-foreground border-border'"
                                    >
                                        {{ opt.key }}
                                    </span>
                                    <span class="flex-1 line-clamp-2">{{ opt.text }}</span>
                                    <CheckCircle2
                                        v-if="getCorrectAnswerKey(q.answer_key) === opt.key"
                                        class="h-4 w-4 text-emerald-600 shrink-0"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Add Question Modal Dialog -->
        <Dialog :open="showAddQuestionModal" @update:open="showAddQuestionModal = $event">
            <DialogContent class="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Tambah Butir Soal Baru</DialogTitle>
                    <DialogDescription>
                        Pilih tipe soal dan lengkapi pertanyaan serta kunci jawaban.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitQuestion" class="space-y-5 py-2">
                    <!-- Type Selector Buttons -->
                    <div class="flex items-center gap-2 border-b pb-4">
                        <Button
                            type="button"
                            size="sm"
                            :variant="questionForm.type === 'multiple_choice' ? 'default' : 'outline'"
                            @click="setQuestionType('multiple_choice')"
                        >
                            Pilihan Ganda
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="questionForm.type === 'essay' ? 'default' : 'outline'"
                            @click="setQuestionType('essay')"
                        >
                            Esai / Uraian
                        </Button>
                    </div>

                    <!-- Question Text -->
                    <div class="space-y-2">
                        <Label for="q_text">Teks Pertanyaan <span class="text-destructive">*</span></Label>
                        <textarea
                            id="q_text"
                            v-model="questionForm.question_text"
                            rows="4"
                            class="w-full rounded-md border border-input bg-background p-3 text-sm focus:outline-hidden focus:ring-2 focus:ring-primary"
                            placeholder="Tuliskan butir soal di sini..."
                            required
                        ></textarea>
                    </div>

                    <!-- Multiple Choice Section -->
                    <div v-if="questionForm.type === 'multiple_choice'" class="space-y-4 pt-2">
                        <div class="flex items-center justify-between">
                            <Label>Opsi Pilihan & Kunci Jawaban</Label>
                            <div class="flex items-center gap-2">
                                <Button
                                    v-if="questionForm.options && questionForm.options.length === 4"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-7 text-xs"
                                    @click="addOptionE"
                                >
                                    + Tambah Opsi E
                                </Button>
                                <Button
                                    v-if="questionForm.options && questionForm.options.length > 4"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-7 text-xs"
                                    @click="removeOptionE"
                                >
                                    - Hapus Opsi E
                                </Button>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(opt, oIdx) in questionForm.options"
                                :key="opt.key"
                                class="flex items-center gap-3 p-2 rounded-lg border bg-card"
                            >
                                <label class="flex items-center gap-1.5 cursor-pointer shrink-0 font-bold text-xs">
                                    <input
                                        v-model="questionForm.answer_key"
                                        type="radio"
                                        name="answer_key_radio"
                                        :value="opt.key"
                                        class="h-4 w-4 text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <span>Opsi {{ opt.key }}</span>
                                </label>
                                <Input
                                    v-model="opt.text"
                                    type="text"
                                    :placeholder="`Masukkan teks untuk pilihan ${opt.key}`"
                                    class="h-9 text-sm"
                                    required
                                />
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Pilih radio button di samping opsi untuk menandainya sebagai <strong>Kunci Jawaban Benar</strong>.
                        </p>
                    </div>

                    <!-- Score Weight -->
                    <div class="space-y-2 pt-2 border-t">
                        <Label for="q_weight">Bobot Skor Nilai</Label>
                        <Input
                            id="q_weight"
                            v-model="questionForm.score_weight"
                            type="number"
                            step="0.5"
                            min="0.5"
                            max="100"
                            class="w-32"
                            required
                        />
                    </div>

                    <DialogFooter class="flex sm:justify-end gap-2 pt-3">
                        <Button type="button" variant="outline" @click="showAddQuestionModal = false">
                            Batal
                        </Button>
                        <Button type="submit" :disabled="questionForm.processing">
                            <span v-if="questionForm.processing">Menyimpan...</span>
                            <span v-else>Simpan Soal</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Question Modal -->
        <Dialog :open="questionToDelete !== null" @update:open="questionToDelete = null">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Konfirmasi Hapus Soal</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus soal ini?
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="flex sm:justify-end gap-2">
                    <Button variant="outline" :disabled="isDeletingQuestion" @click="questionToDelete = null">
                        Batal
                    </Button>
                    <Button variant="destructive" :disabled="isDeletingQuestion" @click="executeDeleteQuestion">
                        <span v-if="isDeletingQuestion">Menghapus...</span>
                        <span v-else>Ya, Hapus Soal</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
