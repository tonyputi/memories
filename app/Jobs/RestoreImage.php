<?php

namespace App\Jobs;

class RestoreImage extends RestoreMedium
{
    public function meta(): array
    {
        $exif = $this->getMetaFromExif($this->path);
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
