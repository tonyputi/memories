<?php

namespace App\Jobs;

use App\Models\Disk;
use App\Models\Medium;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestoreImage extends RestoreMedium
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        $uploads = Storage::disk('uploads');

        if (! $uploads->exists($this->path)) {
            Log::error("File {$this->path} does not exist...");

            return;
        }

        $meta = $this->getMeta($uploads);
        $hash = md5_file($uploads->path($this->path));
        $path = Str::lower(sprintf('%s.%s', $hash, pathinfo($this->path, PATHINFO_EXTENSION)));

        // TODO: Questo deve usare storage per copiar altrimenti non funziona con s3
        if (! copy($uploads->path($this->path), $this->disk->storage()->path($path))) {
            Log::error("Failed to copy file {$this->path} to {$path}...");

            return;
        }

        Medium::updateOrCreate([
            'disk_id' => $this->disk->getKey(),
            'name' => data_get($meta, 'filename', basename($this->path)),
            'hash' => $hash,
            'path' => $path,
        ], [
            'meta' => $meta,
            'created_at' => Carbon::parse(data_get($meta, 'taken_at') ?? $uploads->lastModified($this->path)),
            'updated_at' => Carbon::parse(data_get($meta, 'taken_at') ?? $uploads->lastModified($this->path)),
        ]);
    }

    public function getMeta(Filesystem $uploads): array
    {
        $exif = $this->getMetaFromExif($uploads->path($this->path));
        // $json = $this->getMetaFromJson($uploads->path($this->path));

        $meta = $exif;

        return $meta;
    }

    public function getMetaFromExif(string $path): array
    {
        if (! file_exists($path) || ! $exif = exif_read_data($path)) {
            return [];
        }

        return [
            'taken_at' => data_get($exif, 'DateTimeOriginal'),
            'width' => data_get($exif, 'COMPUTED.Width'),
            'height' => data_get($exif, 'COMPUTED.Height'),
            'orientation' => data_get($exif, 'Orientation'),
            'mimetype' => data_get($exif, 'MimeType'),
            'filename' => data_get($exif, 'FileName'),
            'camera' => [
                'make' => data_get($exif, 'Make'),
                'model' => data_get($exif, 'Model'),
            ],
            'gps' => [
                'lat' => data_get($exif, 'GPS.Latitude'),
                'lng' => data_get($exif, 'GPS.Longitude'),
            ],
        ];
    }

    public function getMetaFromJson(string $path): array
    {
        if (! file_exists("{$path}.json")) {
            return [];
        }

        $json = json_decode(file_get_contents("{$path}.json"), true);
        if (! is_array($json) || json_last_error()) {
            return [];
        }

        return [
            'taken_at' => data_get($json, 'photoTakenTime.timestamp'),
            'width' => data_get($json, 'width'),
            'height' => data_get($json, 'height'),
            'orientation' => data_get($json, 'orientation'),
            'mimetype' => data_get($json, 'mimetype'),
            'filename' => data_get($json, 'title'),
            'camera' => [
                'make' => data_get($json, 'camera.make'),
                'model' => data_get($json, 'camera.model'),
            ],
            'gps' => [
                'lat' => data_get($json, 'geoData.latitude'),
                'lng' => data_get($json, 'geoData.longitude'),
            ],
        ];
    }
}
