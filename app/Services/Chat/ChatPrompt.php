<?php

namespace App\Services\Chat;

/**
 * Builds the RAG chat prompt: the assistant answers ONLY from the user's own
 * transcripts and cites them by title + date.
 */
class ChatPrompt
{
    public static function system(): string
    {
        return <<<'PROMPT'
        Sen, kullanıcının kendi ses kayıtlarından çıkarılmış toplantı/konuşma
        transkriptleri üzerinde soruları yanıtlayan bir asistansın. Görevin, SADECE
        sana verilen transkript alıntılarına dayanarak yanıt vermek.

        Kurallar:
        - Yalnızca aşağıda verilen kaynaklardaki bilgiyi kullan; bilgi uydurma.
        - Yanıtını, transkriptlerin dilinde (genellikle Türkçe) ve net biçimde yaz.
        - Bir bilgiyi kullandığında kaynağını cümle içinde belirt:
          (Kaynak: "<başlık>", <tarih>) biçiminde.
        - Cevap verilen kaynaklarda yoksa, bunu açıkça söyle:
          "Kayıtlarında bu konuda bir bilgi bulamadım." gibi.
        - Kısa, doğrudan ve yardımcı ol. Gereksiz tekrar yapma.
        PROMPT;
    }

    /**
     * @param  array<int, array{transcript_id:int,title:string,date:?string,text:string}>  $sources
     * @param  array<int, array{role:string,content:string}>  $history
     */
    public static function buildUserPrompt(array $sources, array $history, string $question): string
    {
        $parts = [];

        if ($sources === []) {
            $parts[] = "KAYNAKLAR: (kullanıcının ilgili transkripti bulunamadı)";
        } else {
            $parts[] = 'KAYNAKLAR:';
            foreach ($sources as $i => $source) {
                $n = $i + 1;
                $date = $source['date'] ?? 'tarih yok';
                $parts[] = "[$n] Başlık: \"{$source['title']}\" | Tarih: {$date}\n{$source['text']}";
            }
        }

        if ($history !== []) {
            $parts[] = "\nÖNCEKİ KONUŞMA:";
            foreach ($history as $message) {
                $who = $message['role'] === 'assistant' ? 'Asistan' : 'Kullanıcı';
                $parts[] = "{$who}: {$message['content']}";
            }
        }

        $parts[] = "\nSORU: {$question}";
        $parts[] = 'Yukarıdaki kaynaklara dayanarak yanıtla ve kullandığın kaynakları belirt.';

        return implode("\n", $parts);
    }
}
