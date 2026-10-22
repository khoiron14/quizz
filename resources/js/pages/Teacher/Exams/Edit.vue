<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, KeyRound } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

interface ExamData {
    id: string;
    title: string;
    duration_minutes: number;
    start_time: string;
    end_time: string;
    token: string;
    is_randomize: boolean;
    status: 'draft' | 'published' | 'archived';
}

const props = defineProps<{
    exam: ExamData;
}>();

const formatForInput = (isoString: string) => {
    if (!isoString) return '';
    const d = new Date(isoString);
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
};

const form = useForm({
    title: props.exam.title,
    duration_minutes: props.exam.duration_minutes,
    start_time: formatForInput(props.exam.start_time),
    end_time: formatForInput(props.exam.end_time),
    token: props.exam.token,
    is_randomize: props.exam.is_randomize,
    status: props.exam.status,
});

const submit = () => {
    form.put(`/teacher/exams/${props.exam.id}`);
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Kelola Ujian', href: '/teacher/exams' },
            { title: exam.title, href: `/teacher/exams/${exam.id}` },
            { title: 'Edit Ujian', href: `/teacher/exams/${exam.id}/edit` },
        ]"
    >
        <Head :title="`Edit: ${exam.title}`" />

        <div class="flex-1 max-w-3xl p-4 sm:p-6 lg:p-8">
            <div class="mb-6">
                <Button as-child variant="ghost" size="sm" class="mb-2 text-muted-foreground">
                    <Link :href="`/teacher/exams/${exam.id}`">
                        <ArrowLeft class="h-4 w-4 mr-1.5" />
                        Kembali ke Detail Ujian
                    </Link>
                </Button>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    Perbarui Data Ujian
                </h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Sesuaikan durasi pengerjaan, jendela waktu, atau token akses ujian.
                </p>
            </div>

            <Card class="border shadow-xs">
                <form @submit.prevent="submit">
                    <CardHeader>
                        <CardTitle class="text-lg">Konfigurasi Ujian</CardTitle>
                        <CardDescription>
                            Perubahan konfigurasi akan segera berdampak pada sesi peserta.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-6">
                        <!-- Exam Title -->
                        <div class="space-y-2">
                            <Label for="title">Judul Mata Ujian <span class="text-destructive">*</span></Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                type="text"
                                required
                            />
                            <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                        </div>

                        <!-- Duration & Token Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="duration_minutes">Durasi Pengerjaan (Menit) <span class="text-destructive">*</span></Label>
                                <Input
                                    id="duration_minutes"
                                    v-model="form.duration_minutes"
                                    type="number"
                                    min="5"
                                    max="600"
                                    required
                                />
                                <p v-if="form.errors.duration_minutes" class="text-xs text-destructive">{{ form.errors.duration_minutes }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="token">Token Akses (6 Karakter) <span class="text-destructive">*</span></Label>
                                <div class="relative">
                                    <Input
                                        id="token"
                                        v-model="form.token"
                                        type="text"
                                        maxlength="6"
                                        class="font-mono uppercase font-bold tracking-wider"
                                        required
                                        @input="(e: Event) => form.token = ((e.target as HTMLInputElement).value || '').toUpperCase()"
                                    />
                                    <KeyRound class="h-4 w-4 absolute right-3 top-3 text-muted-foreground pointer-events-none" />
                                </div>
                                <p v-if="form.errors.token" class="text-xs text-destructive">{{ form.errors.token }}</p>
                            </div>
                        </div>

                        <!-- Time Window Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="start_time">Jadwal Mulai Ujian <span class="text-destructive">*</span></Label>
                                <Input
                                    id="start_time"
                                    v-model="form.start_time"
                                    type="datetime-local"
                                    required
                                />
                                <p v-if="form.errors.start_time" class="text-xs text-destructive">{{ form.errors.start_time }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="end_time">Jadwal Selesai / Ditutup <span class="text-destructive">*</span></Label>
                                <Input
                                    id="end_time"
                                    v-model="form.end_time"
                                    type="datetime-local"
                                    required
                                />
                                <p v-if="form.errors.end_time" class="text-xs text-destructive">{{ form.errors.end_time }}</p>
                            </div>
                        </div>

                        <!-- Status & Randomize -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t">
                            <div class="space-y-2">
                                <Label for="status">Status Ujian</Label>
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-primary"
                                >
                                    <option value="draft">Draft (Belum dapat diakses siswa)</option>
                                    <option value="published">Published (Aktif sesuai jadwal)</option>
                                    <option value="archived">Archived (Diarsipkan / Selesai)</option>
                                </select>
                            </div>

                            <div class="flex items-center gap-3 pt-6">
                                <input
                                    id="is_randomize"
                                    v-model="form.is_randomize"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                />
                                <Label for="is_randomize" class="text-sm font-normal cursor-pointer">
                                    Acak urutan soal untuk tiap peserta
                                </Label>
                            </div>
                        </div>
                    </CardContent>

                    <CardFooter class="border-t bg-muted/20 flex justify-end gap-3 py-4">
                        <Button as-child variant="outline">
                            <Link :href="`/teacher/exams/${exam.id}`">Batal</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            <span v-if="form.processing">Menyimpan...</span>
                            <span v-else>Perbarui Ujian</span>
                        </Button>
                    </CardFooter>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
