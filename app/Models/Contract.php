<?php

namespace App\Models;

use App\Jobs\DeleteFromDisk;
use App\Jobs\ProcessPdfContract;
use Barryvdh\DomPDF\PDF;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Contract extends Model
{
    /** @use HasFactory<\Database\Factories\ContractFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'shooting_date' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public static function booted()
    {
        static::creating(function (Contract $contract) {
            if (empty($contract->ulid)) {
                $contract->ulid = Str::ulid();
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function photoshoot()
    {
        return $this->belongsTo(Photoshoot::class);
    }

    public function signatures()
    {
        return $this->hasMany(Signature::class);
    }

    public function signaturesRemaining()
    {
        return $this->signatures()->unsigned()->count();
    }

    public function addSignatures($quantity)
    {
        collect(range(1, $quantity))->each(function () {
            $this->signatures()->create();
        });
    }

    public function execute()
    {
        $this->update(['executed_at' => Carbon::now()]);

        ProcessPdfContract::dispatch($this);
    }

    public function download()
    {
        return Storage::disk(config('picstome.disk'))->download(
            $this->pdf_file_path,
            Str::of($this->title)->slug().'.pdf'
        );
    }

    public function isExecuted()
    {
        return $this->executed_at !== null;
    }

    public function updatePdfFile(Pdf $pdf)
    {
        $file_name = Str::random(40);
        $path = "{$this->storage_path}/{$file_name}.pdf";

        Storage::disk(config('picstome.disk'))->put(
            path: $path,
            contents: $pdf->output()
        );

        $this->update(['pdf_file_path' => $path]);
    }

    protected function pdfFileUrl(): Attribute
    {
        return Attribute::get(function (): string {
            return $this->pdf_file_path
                    ? Storage::disk(config('picstome.disk'))->url($this->pdf_file_path)
                    : null;
        });
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function () {
            return "{$this->team->storage_path}/contracts/{$this->ulid}";
        });
    }

    protected function formattedShootingDate(): Attribute
    {
        return Attribute::get(function () {
            return $this->shooting_date?->format('Y-m-d');
        });
    }

    protected function formattedMarkdownBody(): Attribute
    {
        return Attribute::get(function () {
            return Str::of($this->markdown_body)->markdown([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        });
    }

    public function deleteFromDisk()
    {
        if ($this->pdf_file_path) {
            DeleteFromDisk::dispatch($this->pdf_file_path, config('picstome.disk'));
        }

        return $this;
    }

    public function deleteSignatures()
    {
        $this->signatures()->cursor()->each(
            fn (Signature $signature) => $signature->deleteFromDisk()->delete()
        );

        return $this;
    }
}
