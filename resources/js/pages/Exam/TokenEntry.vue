<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, KeyRound, ShieldAlert, Wifi } from '@lucide/vue';
import { ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const form = useForm({
    token: '',
});

const handleTokenInput = (e: Event) => {
    const target = e.target as HTMLInputElement;
    form.token = target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
};

const submitToken = () => {
    if (form.token.length !== 6) {
        return;
    }
    form.post('/exam/start');
};
</script>

<template>
    <Head title="Masuk Ujian CBT" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-muted/40 p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-md space-y-6">
            <!-- Brand / Header -->
            <div class="text-center space-y-2">
                <div class="inline-flex items-center justify-center p-3 rounded-2xl bg-primary/10 text-primary mb-2">
                    <KeyRound class="h-8 w-8" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    Sistem Ujian CBT
                </h1>
                <p class="text-sm text-muted-foreground">
                    Masukkan 6-karakter token ujian yang diberikan oleh pengawas atau guru Anda.
                </p>
            </div>

            <!-- Token Entry Card -->
            <Card class="border-border shadow-lg">
                <CardHeader>
                    <CardTitle class="text-lg">Konfirmasi Token</CardTitle>
                    <CardDescription>
                        Token bersifat sensitif dan mengunci sesi ujian ke akun Anda.
                    </CardDescription>
                </CardHeader>

                <form @submit.prevent="submitToken">
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="token" class="text-sm font-medium">Token Ujian (6 Karakter)</Label>
                            <Input
                                id="token"
                                v-model="form.token"
                                type="text"
                                maxlength="6"
                                placeholder="CONTOH: 7K9M2P"
                                class="h-14 text-center font-mono text-2xl font-bold tracking-widest uppercase transition-all focus:ring-2"
                                :class="{ 'border-destructive focus-visible:ring-destructive': form.errors.token }"
                                autofocus
                                autocomplete="off"
                                @input="handleTokenInput"
                            />
                            <p v-if="form.errors.token" class="text-xs font-medium text-destructive">
                                {{ form.errors.token }}
                            </p>
                        </div>

                        <!-- Local-First Assurance Notice -->
                        <div class="rounded-lg border border-border/60 bg-muted/50 p-3 space-y-2 text-xs text-muted-foreground">
                            <div class="flex items-center gap-2 font-semibold text-foreground">
                                <Wifi class="h-4 w-4 text-emerald-500" />
                                <span>Local-First Zero Data-Loss Active</span>
                            </div>
                            <p>
                                Seluruh soal akan disimpan langsung di browser Anda. Ujian tetap dapat dikerjakan secara offline jika Wi-Fi sekolah mengalami gangguan.
                            </p>
                        </div>
                    </CardContent>

                    <CardFooter>
                        <Button
                            type="submit"
                            size="lg"
                            class="w-full text-base font-medium transition-all"
                            :disabled="form.processing || form.token.length !== 6"
                        >
                            <span v-if="form.processing">Memverifikasi Token...</span>
                            <span v-else class="flex items-center justify-center gap-2">
                                Mulai Kerjakan Ujian
                                <ArrowRight class="h-4 w-4" />
                            </span>
                        </Button>
                    </CardFooter>
                </form>
            </Card>

            <div class="text-center text-xs text-muted-foreground">
                Quizz CBT System &bull; Reliable & Offline Resilient Testing Engine
            </div>
        </div>
    </div>
</template>
