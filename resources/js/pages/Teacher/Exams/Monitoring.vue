<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import {
    Activity,
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    Clock,
    FileCheck,
    Hourglass,
    Plus,
    RefreshCw,
    RotateCcw,
    ShieldAlert,
    UserCheck,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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

interface AttemptRow {
    id: string;
    student: {
        id: string;
        name: string;
        email: string;
    };
    started_at: string;
    submitted_at: string | null;
    extra_time_minutes: number;
    deadline: string;
    status: 'in_progress' | 'submitted' | 'timed_out' | 'reset';
    total_score: number | null;
    answers_count: number;
}

interface ExamInfo {
    id: string;
    title: string;
    token: string;
    duration_minutes: number;
}

const props = defineProps<{
    exam: ExamInfo;
    attempts: AttemptRow[];
}>();

const attemptList = ref<AttemptRow[]>([...props.attempts]);
const isRefreshing = ref<boolean>(false);
const autoRefreshEnabled = ref<boolean>(true);

// Manual or background refresh
const refreshData = async () => {
    isRefreshing.value = true;
    try {
        const res = await fetch(`/teacher/exams/${props.exam.id}/monitoring/status`);
        if (res.ok) {
            const data = await res.json();
            // Update items mapping
            attemptList.value = props.attempts.map((orig) => {
                const updated = data.attempts?.find((a: any) => a.id === orig.id);
                if (updated) {
                    return {
                        ...orig,
                        status: updated.status,
                        extra_time_minutes: updated.extra_time_minutes,
                        deadline: updated.deadline,
                        total_score: updated.total_score,
                        answers_count: updated.answers_count,
                    };
                }
                return orig;
            });
        }
    } catch {
        // Ignore polling network blips
    } finally {
        isRefreshing.value = false;
    }
};

// 5-second polling interval
const { pause, resume } = useIntervalFn(() => {
    if (autoRefreshEnabled.value) {
        refreshData();
    }
}, 5000);

// Extend Time Modal
const targetAttemptForExtendTime = ref<AttemptRow | null>(null);
const extraMinutes = ref<number>(15);
const isExtendingTime = ref<boolean>(false);

const openExtendTimeModal = (attempt: AttemptRow) => {
    targetAttemptForExtendTime.value = attempt;
    extraMinutes.value = 15;
};

const executeExtendTime = () => {
    if (!targetAttemptForExtendTime.value) return;
    isExtendingTime.value = true;

    router.post(
        `/teacher/attempts/${targetAttemptForExtendTime.value.id}/extend-time`,
        { minutes: extraMinutes.value },
        {
            onFinish: () => {
                isExtendingTime.value = false;
                targetAttemptForExtendTime.value = null;
                router.reload({ only: ['attempts'] });
            },
        },
    );
};

// Reset Session Modal
const targetAttemptForReset = ref<AttemptRow | null>(null);
const isResetting = ref<boolean>(false);

const openResetModal = (attempt: AttemptRow) => {
    targetAttemptForReset.value = attempt;
};

const executeResetSession = () => {
    if (!targetAttemptForReset.value) return;
    isResetting.value = true;

    router.post(
        `/teacher/attempts/${targetAttemptForReset.value.id}/reset-session`,
        {},
        {
            onFinish: () => {
                isResetting.value = false;
                targetAttemptForReset.value = null;
                router.reload({ only: ['attempts'] });
            },
        },
    );
};

// Metric stats
const activeCount = computed(() => attemptList.value.filter((a) => a.status === 'in_progress').length);
const submittedCount = computed(() => attemptList.value.filter((a) => a.status === 'submitted').length);

const formatRemainingTime = (deadlineIso: string) => {
    const diff = Math.floor((new Date(deadlineIso).getTime() - Date.now()) / 1000);
    if (diff <= 0) return 'Habis';
    const m = Math.floor(diff / 60);
    const s = diff % 60;
    return `${m}m ${s}s`;
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Kelola Ujian', href: '/teacher/exams' },
            { title: exam.title, href: `/teacher/exams/${exam.id}` },
            { title: 'Live Monitoring', href: `/teacher/exams/${exam.id}/monitoring` },
        ]"
    >
        <Head :title="`Live Monitoring: ${exam.title}`" />

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
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                            Monitoring Peserta Ujian
                        </h1>
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground mt-1">
                        Memantau aktivitas pengerjaan siswa secara langsung dan intervensi darurat pengawas.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-1.5 text-xs"
                        :disabled="isRefreshing"
                        @click="refreshData"
                    >
                        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRefreshing }" />
                        Perbarui Data
                    </Button>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <Card class="border shadow-xs">
                    <CardContent class="p-5 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-medium text-muted-foreground">Sedang Mengerjakan</span>
                            <div class="text-3xl font-extrabold text-primary mt-1">{{ activeCount }}</div>
                        </div>
                        <div class="p-3 rounded-xl bg-primary/10 text-primary">
                            <Activity class="h-6 w-6" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="border shadow-xs">
                    <CardContent class="p-5 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-medium text-muted-foreground">Telah Mengumpulkan</span>
                            <div class="text-3xl font-extrabold text-emerald-600 mt-1">{{ submittedCount }}</div>
                        </div>
                        <div class="p-3 rounded-xl bg-emerald-500/10 text-emerald-600">
                            <CheckCircle2 class="h-6 w-6" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="border shadow-xs">
                    <CardContent class="p-5 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-medium text-muted-foreground">Total Peserta Terdaftar</span>
                            <div class="text-3xl font-extrabold text-foreground mt-1">{{ attemptList.length }}</div>
                        </div>
                        <div class="p-3 rounded-xl bg-muted text-foreground">
                            <Users class="h-6 w-6" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Student Attempts Table -->
            <Card class="border shadow-xs overflow-hidden">
                <CardHeader class="pb-3 border-b bg-card">
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base font-bold">Daftar Sesi Siswa</CardTitle>
                        <span class="text-xs text-muted-foreground font-mono">
                            Auto-refresh tiap 5 detik
                        </span>
                    </div>
                </CardHeader>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/50 border-b text-xs font-bold text-muted-foreground uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Nama Siswa</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Sisa Waktu</th>
                                <th class="px-4 py-3">Ekstra</th>
                                <th class="px-4 py-3">Progres Jawaban</th>
                                <th class="px-4 py-3">Skor</th>
                                <th class="px-4 py-3 text-right">Intervensi Pengawas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="attemptList.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-muted-foreground text-sm">
                                    Belum ada siswa yang memasukkan token ujian ini.
                                </td>
                            </tr>
                            <tr
                                v-for="att in attemptList"
                                :key="att.id"
                                class="hover:bg-muted/30 transition-colors"
                            >
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-foreground">{{ att.student.name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ att.student.email }}</div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <Badge
                                        :variant="att.status === 'in_progress' ? 'default' : att.status === 'submitted' ? 'secondary' : 'destructive'"
                                        class="capitalize text-xs font-semibold"
                                        :class="att.status === 'submitted' ? 'bg-emerald-600 text-white' : ''"
                                    >
                                        {{ att.status.replace('_', ' ') }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3.5 font-mono text-xs">
                                    <span v-if="att.status === 'in_progress'" class="font-semibold text-primary">
                                        {{ formatRemainingTime(att.deadline) }}
                                    </span>
                                    <span v-else class="text-muted-foreground">-</span>
                                </td>
                                <td class="px-4 py-3.5 text-xs font-medium">
                                    <span v-if="att.extra_time_minutes > 0" class="text-amber-600 font-bold">
                                        +{{ att.extra_time_minutes }}m
                                    </span>
                                    <span v-else class="text-muted-foreground">0m</span>
                                </td>
                                <td class="px-4 py-3.5 text-xs">
                                    <span class="font-bold text-foreground">{{ att.answers_count }}</span> Soal Tersimpan
                                </td>
                                <td class="px-4 py-3.5 font-bold text-sm">
                                    <span v-if="att.total_score !== null" class="text-primary">{{ att.total_score }}</span>
                                    <span v-else class="text-muted-foreground text-xs">-</span>
                                </td>
                                <td class="px-4 py-3.5 text-right space-x-2 whitespace-nowrap">
                                    <!-- Extend Time Button -->
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="text-amber-600 border-amber-300 hover:bg-amber-50 dark:hover:bg-amber-950/30 h-7 px-2 text-xs"
                                        @click="openExtendTimeModal(att)"
                                    >
                                        <Clock class="h-3 w-3 mr-1" />
                                        +Waktu
                                    </Button>

                                    <!-- Reset Session Button -->
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="text-destructive border-destructive/30 hover:bg-destructive/10 h-7 px-2 text-xs"
                                        title="Buka kembali sesi siswa yang terkunci atau mengalami komputer mati"
                                        @click="openResetModal(att)"
                                    >
                                        <RotateCcw class="h-3 w-3 mr-1" />
                                        Reset Sesi
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <!-- Extend Time Dialog -->
        <Dialog :open="targetAttemptForExtendTime !== null" @update:open="targetAttemptForExtendTime = null">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Perpanjang Waktu Ujian</DialogTitle>
                    <DialogDescription>
                        Tambahkan menit tambahan bagi siswa <strong>{{ targetAttemptForExtendTime?.student.name }}</strong> (misal karena kendala perangkat).
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-3">
                    <div class="space-y-2">
                        <Label for="extra_mins">Jumlah Menit Tambahan</Label>
                        <Input
                            id="extra_mins"
                            v-model="extraMinutes"
                            type="number"
                            min="1"
                            max="180"
                            class="text-lg font-bold"
                        />
                    </div>
                    <div class="flex gap-2">
                        <Button type="button" variant="outline" size="sm" class="h-8 text-xs" @click="extraMinutes = 5">+5 Menit</Button>
                        <Button type="button" variant="outline" size="sm" class="h-8 text-xs" @click="extraMinutes = 10">+10 Menit</Button>
                        <Button type="button" variant="outline" size="sm" class="h-8 text-xs" @click="extraMinutes = 15">+15 Menit</Button>
                        <Button type="button" variant="outline" size="sm" class="h-8 text-xs" @click="extraMinutes = 30">+30 Menit</Button>
                    </div>
                </div>

                <DialogFooter class="flex sm:justify-end gap-2">
                    <Button variant="outline" :disabled="isExtendingTime" @click="targetAttemptForExtendTime = null">
                        Batal
                    </Button>
                    <Button variant="default" :disabled="isExtendingTime" @click="executeExtendTime">
                        <span v-if="isExtendingTime">Menambahkan...</span>
                        <span v-else>Tambahkan Waktu</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Reset Session Dialog -->
        <Dialog :open="targetAttemptForReset !== null" @update:open="targetAttemptForReset = null">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reset Sesi Siswa (Emergency)</DialogTitle>
                    <DialogDescription>
                        Buka kunci pengerjaan untuk <strong>{{ targetAttemptForReset?.student.name }}</strong>. Status ujian akan dikembalikan ke <em>In Progress</em> dan siswa dapat masuk kembali.
                    </DialogDescription>
                </DialogHeader>

                <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs text-emerald-800 dark:text-emerald-300">
                    <strong>Zero Data-Loss:</strong> Seluruh jawaban yang sudah tersimpan sebelumnya <strong>TIDAK AKAN HILANG</strong>.
                </div>

                <DialogFooter class="flex sm:justify-end gap-2">
                    <Button variant="outline" :disabled="isResetting" @click="targetAttemptForReset = null">
                        Batal
                    </Button>
                    <Button variant="destructive" :disabled="isResetting" @click="executeResetSession">
                        <span v-if="isResetting">Membuka Sesi...</span>
                        <span v-else>Buka Sesi Siswa</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
