<?php
namespace App\Vars;

class FileProcessing
{

    // process pdf file with default configuration
    // return preview and processed file
    public static function process_pdf_file($pdf_file_path, $linking_date = null)
    {
        $file_base_name = \basename($pdf_file_path);

        if( !self::compress_pdf($pdf_file_path) ) {
            logger('Compression did not work: '. $file_base_name);
        }

        // start - add watermark to the file
        if( !self::update_pdf($pdf_file_path) ) {
            logger('Could not add watermark to the file: '.$file_base_name);
        }
        // end - add watermark to the file

        // start - link headlines in the pdf
        if( $linking_date !== null && !self::link_headlines($pdf_file_path, $linking_date) ) {
            logger('Could not perform linking: '.$file_base_name);
        }
        // end - link headlines in the pdf

        // start - create a preview file
        $output_preview_file_path = \str_replace($file_base_name, '-preview-' . $file_base_name, $pdf_file_path);
        // $preview_file = self::update_pdf($pdf_file_path, $output_preview_file_path, false, 20);
        $preview_file = self::compress_pdf($pdf_file_path, $output_preview_file_path);

        if( ! $preview_file ) {
            logger('Could not generate preview file: '. $file_base_name);
        }
        // end - create a preview file

        return [$preview_file, $pdf_file_path];
    }

    // pass $preview_file_path to create a preview
    public static function compress_pdf($pdf_file_path, $preview_file_path = null)
    {
        if( ! file_exists($pdf_file_path) ) {
            return false;
        }

        $basename = \basename($pdf_file_path);
        $output_preview_file_path = \str_replace($basename, 'c-' . $basename, $pdf_file_path);

        $output = [];

        // screen - lowest quality - 72dpi - 76MB-8MB
        // ebook - slightly better - 150dpi - 76MB-11MB
        $quality = 'ebook';

        if( $preview_file_path ) {
            $command  = "gs -q -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/{$quality} -dFastWebView -dNOPAUSE -dBATCH -dFirstPage=1 -dLastPage=2 -sOutputFile={$preview_file_path} {$pdf_file_path}";

            exec($command, $output);

            return \file_exists($preview_file_path)
                ? $preview_file_path
                : false;
        }

        $command  = "gs -q -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/{$quality} -dFastWebView -dNOPAUSE -dBATCH -sOutputFile={$output_preview_file_path} {$pdf_file_path}";
        $command .= "&& mv {$output_preview_file_path} {$pdf_file_path}";

        exec($command, $output);

        return \file_exists($pdf_file_path)
            ? $pdf_file_path
            : false;
    }

    // add watermark to create pdf
    // specify page_count_percent to create preview of this pdf
    public static function update_pdf($pdf_file_path, $output_file_path = null, $add_watermark = true, $page_count_percent = 0)
    {
        $watermark = public_path('assets/frontend/img/logo_big_wr.png');

        if( file_exists($pdf_file_path) ) {
            try {

                if( !file_exists($watermark) && $add_watermark ) {
                    throw new \Exception('watermark logo does not exist');
                }

                $pdf = new \Mpdf\Mpdf(
                    ['tempDir' => storage_path('temp')]
                );
    
                $pdf->SetAutoPageBreak(false);

                $page_count = 1;
                $totalPages = $pdf->setSourceFile($pdf_file_path);

                if( $page_count_percent > 0 ) {
                    // $page_count = intval(floor($totalPages * ($page_count_percent/100)));
                    // $page_count = $page_count > 0 ? $page_count : 1;
                    $page_count = $totalPages > 2 ? 2 : 1;
                } else {
                    $page_count = $totalPages;
                }

                $got_size = false;

                for( $i =1; $i<=$page_count; $i++ ) {
                    $tpl = $pdf->importPage($i);
                    $size = $pdf->getTemplateSize($tpl);

                    // figure out page size, create new pdf inst. and reset the loop
                    // if( isset($size['width']) && isset($size['height']) && !$got_size ) {
                    //     $got_size = true;
                    //     $pdf = new \Mpdf\Mpdf(
                    //         [
                    //             'tempDir' => storage_path('temp'),'mode' => 'utf-8',
                    //             'format' => [ceil($size['width'] + 6), ceil($size['height'] + 8)]
                    //         ]
                    //     );
                    //     $pdf->SetAutoPageBreak(false);
                    //     $pdf->setSourceFile($pdf_file_path);
                    //     $i = 0;
                    //     continue;
                    // }

                    // set width and height for this page
                    $oientation = 'P';
                    $pdf->_setPageSize([ceil($size['width'] + 6), ceil($size['height'] + 8)], $oientation);

                    $pdf->AddPage();
                    $pdf->useTemplate($tpl);

                    if( $add_watermark ) {
                        $pdf->SetWatermarkImage($watermark, 0.1);
                        $pdf->showWatermarkImage = true;
                    }

                    if( $page_count_percent > 0 ) {
                        $pdf->SetWatermarkText('PREVIEW');
                        $pdf->showWatermarkText = true;
                    }
                }

                // new file path or just replace the old
                $finalPath = $output_file_path ?? $pdf_file_path;
    
                $pdf->Output(
                    $finalPath,
                    \Mpdf\Output\Destination::FILE
                );

                if( file_exists($finalPath) ) {
                    return $finalPath;
                }
            } catch(\Exception $e) {
                logger($e->getMessage());
            }
        }

        return false;
    }

    // sudo -v && wget -nv -O- https://download.calibre-ebook.com/linux-installer.sh | sudo sh /dev/stdin
    public static function epub_to_pdf($ebook_file_path, $pdf_file_path)
    {
        if( is_readable($ebook_file_path) ) {
            $output = [];
            exec("ebook-convert $ebook_file_path $pdf_file_path", $output);

            if( file_exists($pdf_file_path) ) {
                return $pdf_file_path;
            }
        }

        return false;
    }

    // instructions:
    // python3.8 must be installed for it

    // env directory inside PyPdf is the complete setup it needs
    // ln -sf env/bin/python - /usr/bin/python3.8 --- or whereever the python3.8 executable is
    // bash env/bin/activate

    // link headlines in pdf with articles exist in the system
    public static function link_headlines($file_path, $date = null)
    {
        if( !file_exists($file_path) ) {
            return false;
        }

        $script_v = app_path('Vars/PyPdf/env/bin/python');

        $script_path = app_path('Vars/PyPdf/linkheadline.py');
        $execute = "{$script_v} {$script_path} {$file_path}";

        if( $date && ($date = \strtotime($date)) ) {
            $execute .= ' '.date('Y-m-d', $date);
        }

        logger('performing linking: '.$script_path);

        $output = trim( shell_exec(escapeshellcmd($execute).' 2>&1') );

        if( $output == '1' ) {
            if( file_exists($file_path) ) {
                return $file_path;
            }
        }
        
        return 0;
    }
}