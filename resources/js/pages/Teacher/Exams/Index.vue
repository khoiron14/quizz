<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    BookOpen,
    Calendar,
    Check,
    Clock,
    Copy,
    Edit3,
    FilePlus2,
    GraduationCap,
    HelpCircle,
    MoreVertical,
    Plus,
    Trash2,
    Users,
} from '@lucide/vue';
import { ref } from 'vue';
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
import { useCan } from '@/composables/useCan';
import AppLayout from '@/layouts/AppLayout.vue';

interface ExamItem {
    id: string;
    title: string;
    token: string;
    duration_minutes: number;
    start_time: string;
    end_time: string;
    is_randomize: boolean;
    status: 'draft' | 'published' | 'archived';
    questions_count?: number;
    attempts_count?: number;
    created_at: string;
}

const props = defineProps<{
    exams: ExamItem[];
}>();

const { can } = useCan();

// Token copying
const copiedToken = ref<string | null>(null);
const copyToken = (token: string) => {
    navigator.clipboard.writeText(token);
    copiedToken.value = token;
    setTimeout(() => {
        copiedToken.value = null;
    }, 2000);
};

// Delete confirmation modal
const examToDelete = ref<ExamItem | null>(null);
const isDeleting = ref<boolean>(false);

const confirmDelete = (exam: ExamItem) => {
    examToDelete.value = exam;
};

const executeDelete = () => {
    if (!examToDelete.value) return;
    isDeleting.value = true;
    router.delete(`/teacher/exams/${examToDelete.value.id}`, {
        onFinish: () => {
            isDeleting.value = false;
            examToDelete.value = null;
        },
    });
};

const formatDateTime = (iso: string) => {
    return new Date(iso).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Kelola Ujian', href: '/teacher/exams' },
        ]"
    >
        <Head title="Kelola Ujian CBT" />

        <div class="flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Kelola Ujian CBT
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Daftar jadwal ujian, authoring soal, dan pemantauan peserta secara real-time.
                    </p>
                </div>

                <div v-if="can('exams.create')">
                    <Button as-child class="gap-2 shadow-xs">
                        <Link href="/teacher/exams/create">
                            <Plus class="h-4 w-4" />
                            Buat Ujian Baru
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Empty State -->
            <Card v-if="exams.length === 0" class="border-dashed py-12 text-center">
                <CardContent class="space-y-4">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <GraduationCap class="h-7 w-7" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-lg font-semibold text-foreground">Belum Ada Ujian</h3>
                        <p class="text-sm text-muted-foreground max-w-sm mx-auto">
                            Anda belum membuat jadwal ujian CBT. Mulai dengan membuat ujian dan menambahkan bank soal.
                        </p>
                    </div>
                    <Button v-if="can('exams.create')" as-child class="gap-2">
                        <Link href="/teacher/exams/create">
                            <Plus class="h-4 w-4" />
                            Buat Ujian Sekarang
                        </Link>
                    </Button>
                </CardContent>
            </Card>

            <!-- Exams Cards Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Card
                    v-for="exam in exams"
                    :key="exam.id"
                    class="flex flex-col justify-between border transition-all hover:shadow-md"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <Badge
                                :variant="exam.status === 'published' ? 'default' : exam.status === 'draft' ? 'secondary' : 'outline'"
                                class="capitalize text-xs font-semibold"
                                :class="exam.status === 'published' ? 'bg-emerald-600 text-white hover:bg-emerald-700' : ''"
                            >
                                {{ exam.status }}
                            </Badge>

                            <!-- Token Copy Pill -->
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md font-mono text-xs font-bold bg-muted hover:bg-muted/80 transition-colors border"
                                :title="'Klik untuk menyalin token ' + exam.token"
                                @click="copyToken(exam.token)"
                            >
                                <span>Token: {{ exam.token }}</span>
                                <Check v-if="copiedToken === exam.token" class="h-3.5 w-3.5 text-emerald-600" />
                                <Copy v-else class="h-3.5 w-3.5 text-muted-foreground" />
                            </button>
                        </div>

                        <CardTitle class="text-lg font-bold line-clamp-2 mt-3">
                            {{ exam.title }}
                        </CardTitle>
                    </CardHeader>

                    <CardContent class="space-y-3 text-xs text-muted-foreground py-2 flex-1">
                        <div class="flex items-center gap-2">
                            <Clock class="h-4 w-4 shrink-0 text-foreground/70" />
                            <span>Durasi: <strong>{{ exam.duration_minutes }} Menit</strong></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Calendar class="h-4 w-4 shrink-0 text-foreground/70" />
                            <span class="line-clamp-1">Mulai: {{ formatDateTime(exam.start_time) }}</span>
                        </div>
                        <div class="flex items-center gap-4 pt-2 border-t font-medium text-foreground">
                            <div class="flex items-center gap-1.5">
                                <HelpCircle class="h-4 w-4 text-primary" />
                                <span>{{ exam.questions_count ?? 0 }} Soal</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <Users class="h-4 w-4 text-emerald-600" />
                                <span>{{ exam.attempts_count ?? 0 }} Peserta</span>
                            </div>
                        </div>
                    </CardContent>

                    <CardFooter class="pt-3 border-t bg-muted/20 flex flex-wrap gap-2 justify-between items-center">
                        <div class="flex items-center gap-1.5">
                            <Button as-child variant="outline" size="sm" class="text-xs h-8">
                                <Link :href="`/teacher/exams/${exam.id}`">
                                    Soal
                                </Link>
                            </Button>
                            <Button as-child variant="outline" size="sm" class="text-xs h-8 text-primary border-primary/30 hover:bg-primary/5">
                                <Link :href="`/teacher/exams/${exam.id}/monitoring`">
                                    <Activity class="h-3.5 w-3.5 mr-1" />
                                    Live Monitor
                                </Link>
                            </Button>
                            <Button as-child variant="ghost" size="sm" class="text-xs h-8 text-muted-foreground">
                                <Link :href="`/teacher/exams/${exam.id}/grading`">
                                    Nilai
                                </Link>
                            </Button>
                        </div>

                        <div class="flex items-center gap-1">
                            <Button as-child variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-foreground">
                                <Link :href="`/teacher/exams/${exam.id}/edit`">
                                    <Edit3 class="h-4 w-4" />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8 text-destructive/70 hover:text-destructive hover:bg-destructive/10"
                                @click="confirmDelete(exam)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardFooter>
                </Card>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Dialog :open="examToDelete !== null" @update:open="examToDelete = null">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Konfirmasi Hapus Ujian</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus ujian <strong>{{ examToDelete?.title }}</strong>? Seluruh data soal dan riwayat pengerjaan siswa akan dihapus permanen.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="flex sm:justify-end gap-2">
                    <Button variant="outline" :disabled="isDeleting" @click="examToDelete = null">
                        Batal
                    </Button>
                    <Button variant="destructive" :disabled="isDeleting" @click="executeDelete">
                        <span v-if="isDeleting">Menghapus...</span>
                        <span v-else>Ya, Hapus Ujian</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

