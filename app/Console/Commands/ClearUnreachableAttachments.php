<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use App\Models\Balance;

class ClearUnreachableAttachments extends Command
{
    protected $signature = 'attachments:clear-unreachable';

    protected $description = 'Clear attachments that are not related to any model';

    public function handle()
    {
        $invoices = $this->listUnreachableInvoicesAttachments();
        $balances = $this->listUnreachableBalancesAttachments();


        $this->info('Unreachable attachments found:');
        $this->info('   Invoices: ' . $invoices['count'] . ' attachments, ' . $invoices['size'] . ' MB');
        $this->info('   Balances: ' . $balances['count'] . ' attachments, ' . $balances['size'] . ' MB');
        $this->info('   ');

        $this->info('Total attachments: ' . ($invoices['count'] + $balances['count']) . ' attachments, ' . ($invoices['size'] + $balances['size']) . ' MB');

        $this->deleteAttachments($invoices['items']);
        $this->deleteAttachments($balances['items']);

        $this->info('Unreachable attachments removed successfully ✅');
    }


    private function listUnreachableInvoicesAttachments()
    {
        $attachmentsItems = Storage::disk('public')->files('invoices');
        $attachmentsItemsIds = collect($attachmentsItems)->map(function($item){
            return basename($item);
        });

        $invoicesAttachmentsIds = Invoice::all()->filter(function($invoice){
            return !is_null($invoice->image);
        })->map(function($invoice){
            return $invoice->image;
        });

        $unreachableAttachments = $attachmentsItemsIds->filter(function($attachmentId) use ($invoicesAttachmentsIds){
            return !$invoicesAttachmentsIds->contains($attachmentId);
        });

        $totalUnreachableAttachments = $unreachableAttachments->count();
        $totalUnreachableAttachmentsInMb = $unreachableAttachments->map(function($attachmentId){
            return Storage::disk('public')->size('invoices/' . $attachmentId);
        })->sum() / 1024 / 1024;

        return [
            'count' => $totalUnreachableAttachments,
            'size' => $totalUnreachableAttachmentsInMb,
            'items' => $unreachableAttachments->map(function($item){
                return 'invoices/' . $item;
            })->toArray()
        ];
    }

    private function listUnreachableBalancesAttachments()
    {
        $attachmentsItems = Storage::disk('public')->files('balances');
        $attachmentsItemsIds = collect($attachmentsItems)->map(function($item){
            return basename($item);
        });

        $balancesAttachmentsIds = Balance::all()->map(function($balance){
            return $balance->id;
        });

        $unreachableAttachments = $attachmentsItemsIds->filter(function($attachmentId) use ($balancesAttachmentsIds){
            return (!$balancesAttachmentsIds->contains($attachmentId));
        });

        $totalUnreachableAttachments = $unreachableAttachments->count();
        $totalUnreachableAttachmentsInMb = $unreachableAttachments->map(function($attachmentId){
            return Storage::disk('public')->size('balances/' . $attachmentId);
        })->sum() / 1024 / 1024;


        return [
            'count' => $totalUnreachableAttachments,
            'size' => $totalUnreachableAttachmentsInMb,
            'items' => $unreachableAttachments->map(function($item){
                return 'balances/' . $item;
            })->toArray()
        ];
    }

    private function listUnreachableWwarehouseChatsAttachments()
    {
        return [];
    }


    private function deleteAttachments($attachmentsPaths)
    {
        Collection::make($attachmentsPaths)->each(function ($file) {
            Storage::disk('public')->delete($file);
        });
    }
}
