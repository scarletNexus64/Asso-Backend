<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Services\FirebaseMessagingService;
use Illuminate\Console\Command;

class SubscribeTokensToTopic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:subscribe-topic {topic=all_users} {--batch-size=500}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Abonne tous les tokens actifs à un topic FCM (par défaut: all_users)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $topic = $this->argument('topic');
        $batchSize = (int) $this->option('batch-size');

        $this->info("🚀 Démarrage de l'abonnement au topic: {$topic}");

        // Récupérer tous les tokens actifs
        $tokens = DeviceToken::active()->pluck('token')->toArray();

        if (empty($tokens)) {
            $this->warn('⚠️  Aucun token actif trouvé.');
            return Command::SUCCESS;
        }

        $totalTokens = count($tokens);
        $this->info("📱 {$totalTokens} token(s) actif(s) trouvé(s)");

        // Diviser les tokens en lots pour éviter les erreurs de limite
        $batches = array_chunk($tokens, $batchSize);
        $totalBatches = count($batches);

        $this->info("📦 Division en {$totalBatches} batch(s) de {$batchSize} token(s)");

        $fcmService = new FirebaseMessagingService();
        $progressBar = $this->output->createProgressBar($totalBatches);
        $progressBar->start();

        $totalSuccess = 0;
        $totalFailed = 0;

        foreach ($batches as $index => $batch) {
            try {
                $result = $fcmService->subscribeToTopic($batch, $topic);

                if ($result['success']) {
                    $totalSuccess += count($batch);
                } else {
                    $totalFailed += count($batch);
                    $this->newLine();
                    $this->error("❌ Erreur batch " . ($index + 1) . ": " . ($result['error'] ?? $result['message']));
                }

                $progressBar->advance();

                // Pause courte entre les batchs pour éviter de surcharger l'API Firebase
                if ($index < $totalBatches - 1) {
                    usleep(100000); // 100ms
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("❌ Erreur lors du batch " . ($index + 1) . ": " . $e->getMessage());
                $totalFailed += count($batch);
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Afficher le résumé
        $this->info('✅ Abonnement terminé!');
        $this->table(
            ['Statistique', 'Valeur'],
            [
                ['Topic', $topic],
                ['Total tokens', $totalTokens],
                ['Succès', $totalSuccess],
                ['Échecs', $totalFailed],
                ['Batches traités', $totalBatches],
            ]
        );

        return Command::SUCCESS;
    }
}
