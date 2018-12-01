<?php
/**
 * Created by PhpStorm.
 * User: gerizuna
 * Date: 01.12.18
 * Time: 10:58
 */

namespace mod_ddtaquiz\output;


class modal_render extends \html_writer
{
    public static function createModal($title,$body,$yesButton,$attr):string{
        $output = '';
        $modalTileId = 'modalTile' . time();

        $output .= self::start_div('modal',[
            'id'=>(array_key_exists('id',$attr))? $attr['id']:('modal'.time()),
            'tabindex'=>"-1",
            'role'=>"dialog",
            'aria-labelledby'=>$modalTileId,
            'aria-hidden'=>"true"
        ]);

            $output .=  self::start_div('modal-dialog',['role'=>'document']);

                $output .= self::start_div('modal-content');
                    $output .= self::start_div('modal-header');
                        $output .= self::start_tag('h5',[
                            'class'=>"modal-title",
                            'id'=>$modalTileId
                        ]);
                        $output .= $title;
                        $output .= self::end_tag('h5');

                        $output .= self::start_tag('button',[
                            'class'=>"close",
                            'data-dismiss'=>"modal",
                            'aria-label'=>"Close"
                        ]);
                            $output .= self::start_tag('span', ['aria-hidden'=>'true']);
                                $output .= '&times;';
                            $output .= self::end_tag('span');
                        $output .= self::end_tag('button');
                    $output .= self::end_div();

                    $output .= self::start_div('modal-body');
                        $output .= $body;
                    $output .= self::end_div();

                    $output .= self::start_div('modal-footer');

                        $output .= $yesButton;

                        $output .= self::start_tag('button',[
                            'class'=>"btn btn-light",
                            'data-dismiss'=>"modal"
                        ]);
                            $output .= 'Cancel';
                        $output .= self::end_tag('button');

                    $output .= self::end_div();
                $output .= self::end_div();
            $output .= self::end_div();
        $output .= self::end_div();

        return $output;
    }

    public static function createModalTrigger($modalId, $triggerType, $triggerText, $attr):string{
        $output = '';
        $output .= self::start_tag($triggerType,[
            'id'=>(array_key_exists('id',$attr))? $attr['id']:('modalTrigger'.time()),
            'class'=>(array_key_exists('class',$attr))? $attr['class']:('btn btn-primary'),
            'data-toggle'=>"modal",
            'data-target'=>"#". $modalId
        ]);
            $output .= $triggerText;
        $output .= self::end_tag($triggerType);

        return $output;
    }
}