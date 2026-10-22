<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Award, CheckCircle2, Home } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    attempt: {
        id: string;
        status: string;
        started_at: string;
        submitted_at: string | null;
        total_score: number | null;
    };
    exam: {
        id: string;
        title: string;
    };
    answersCount: number;
}

const props = defineProps<Props>();

const formatDate = (isoString?: string | null) => {
    if (!isoString) return '-';
    return new Date(isoString).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};
</script>

<template>
    <Head title="Ujian Berhasil Dikumpulkan" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-muted/40 p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-lg space-y-6">
            <Card class="border shadow-lg text-center overflow-hidden">
                <!-- Top Header Banner -->
                <div class="bg-emerald-600/10 dark:bg-emerald-950/30 py-8 px-6 border-b border-emerald-500/20">
                    <div class="inline-flex items-center justify-center p-3 rounded-full bg-emerald-500 text-white mb-3 shadow-md">
                        <CheckCircle2 class="h-10 w-10" />
                    </div>
                    <CardTitle class="text-2xl font-bold tracking-tight text-foreground">
                        Ujian Telah Selesai!
                    </CardTitle>
                    <CardDescription class="text-sm mt-1 max-w-sm mx-auto">
                        Seluruh jawaban Anda telah tersimpan dan diverifikasi oleh sistem CBT.
                    </CardDescription>
                </div>

                <CardContent class="pt-6 pb-6 space-y-5 text-left">
                    <div class="rounded-xl border bg-card p-4 space-y-3">
                        <div class="flex items-center justify-between text-sm pb-2 border-b">
                            <span class="text-muted-foreground">Mata Ujian</span>
                            <span class="font-bold text-foreground">{{ exam.title }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm pb-2 border-b">
                            <span class="text-muted-foreground">Waktu Pengumpulan</span>
                            <span class="font-medium text-foreground">{{ formatDate(attempt.submitted_at) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm pb-2 border-b">
                            <span class="text-muted-foreground">Jawaban Tersimpan</span>
                            <span class="font-semibold text-emerald-600">{{ answersCount }} Soal</span>
                        </div>
                        <div v-if="attempt.total_score !== null" class="flex items-center justify-between text-sm pt-1">
                            <span class="font-semibold text-foreground flex items-center gap-1.5">
                                <Award class="h-4 w-4 text-amber-500" />
                                Nilai Terkalkulasi (PG)
                            </span>
                            <span class="text-lg font-bold text-primary">{{ attempt.total_score }} Poin</span>
                        </div>
                    </div>

                    <p class="text-xs text-muted-foreground text-center">
                        Jika terdapat soal esai, nilai akhir akan diperbarui setelah guru selesai memeriksa jawaban Anda.
                    </p>
                </CardContent>

                <CardFooter class="pt-0">
                    <Button as-child class="w-full" size="lg">
                        <Link href="/dashboard" class="flex items-center justify-center gap-2">
                            <Home class="h-4 w-4" />
                            Kembali ke Halaman Utama
                        </Link>
                    </Button>
                </CardFooter>
            </Card>
        </div>
    </div>
</template>
