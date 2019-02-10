<?php
/**
 * Created by PhpStorm.
 * User: gerizuna
 * Date: 01.12.18
 * Time: 10:58
 */

namespace mod_ddtaquiz\output;


class ddtaquiz_bootstrap_render extends \html_writer
{
    public static function createModal($title,$body,$yesButton,$attr):string{
        $output = '';
        $modalTileId = 'modalTile' . time();

        $output .= self::start_div('modal',[
            'id'=>(is_array($attr) && array_key_exists('id',$attr))? $attr['id']:('modal'.time()),
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
            'id'=>(is_array($attr) && array_key_exists('id',$attr))? $attr['id']:('modalTrigger'.time()),
            'class'=>(is_array($attr) && array_key_exists('class',$attr))? $attr['class']:('btn btn-primary'),
            'data-toggle'=>"modal",
            'data-target'=>"#". $modalId
        ]);
            $output .= $triggerText;
        $output .= self::end_tag($triggerType);

        return $output;
    }

    public static function  createCard($cardBody, $cardHeader = null, $cardFooter= null):string {
        $output = '';
        $output .= self::start_div('card mb-5');
            if($cardHeader){
                $output .= self::start_div('card-header');
                    $output .= $cardHeader;
                $output .= self::end_div();
            }
            $output .= self::start_div('card-body');
                $output .= $cardBody;
            $output .= self::end_div();
            if($cardFooter){
                $output .= self::start_div('card-footer');
                    $output .= $cardFooter;
                $output .= self::end_div();
            }
        $output .= self::end_div();

        return $output;
    }

    public static function createAccordion ($accordionId,$children):string {
       $output =
           self::start_div('accordion', array('id' => $accordionId)).
           $children.
           self::end_div();
       return $output;
    }

    public static function createAccordionHeader ($preContent, $content, $postContent, $attr = null , $collapseId = null):string {
        $output =
            self::start_div('card-header', [
                'id' => (is_array($attr) && array_key_exists('id',$attr))?$attr['id']:'',
                'class' => (is_array($attr) && array_key_exists('class',$attr))?$attr['class']:'',
            ]).
            self::start_tag('h5',['class'=>'mb-0 domain-wrapper']).
            $preContent;

        if($collapseId){
            $output .=
                self::start_tag('span',[
                    'data-toggle'=>"collapse",
                    'data-target'=>"#" . $collapseId,
                    'aria-expanded'=>"true",
                    'aria-controls'=>$collapseId
                ]).
                $content.
                self::end_tag('span');
        }else{
            $output .= $content;
        }

        $output .=
            $postContent.
            self::end_tag('h5').
            self::end_div();

        return $output;
    }

    public static function createAccordionCollapsible($collapseId,$triggerId, $accordionId, $content):string{
        $output =
            self::start_div('collapse',[
                'id' => $collapseId,
                'aria-labelledby'=>$triggerId,
                'data-parent'=>"#". $accordionId
            ]).
            self::start_div('card-body').
            $content.
            self::end_div().
            self::end_div();

        return $output;
    }

    public static function createDomainCheckboxes($qid, $domains):string{
        global $DB;
        $qInstance = $DB->get_record("ddtaquiz_qinstance", ["id" => $qid]);
        $activeDomains = null;
        if ($qInstance->domains) {
            $activeDomains = explode(",", $qInstance->domains);
        }
        $output = self::start_div("domain-content");
        foreach ($domains as $domain) {
            $domain = trim($domain);
            if (in_array("$domain", $activeDomains)) {
                $output .=
                    self::start_div("domain-checkbox") .
                    self::start_tag("input", [
                        "class" => "form-check-input",
                        "type" => "checkbox",
                        "id" => $qid . "-" . $domain,
                        "value" => "",
                        "checked" => "",
                        "name" => "domain" . "-" . $qid . "-" . $domain
                    ]);
            } else {
                $output .=
                    self::start_div("domain-checkbox") .
                    self::start_tag("input", [
                        "class" => "form-check-input",
                        "type" => "checkbox",
                        "id" => $qid . "-" . $domain,
                        "value" => "",
                        "name" => "domain" . "-" . $qid . "-" . $domain
                    ]);
            }
            $output .=
                    self::start_tag("label", [
                        "class" => "form-check-label",
                        "for" => $qid."-".$domain
                    ]).
                    $domain.
                    self::end_tag("label").
                self::end_div();
        }
        $output .= self::end_div();

        return $output;
    }

    public static function createAlert($type,$content): string{
        $output = '';
        $output .=
            self::start_div('alert ddtaquiz-alerts alert-'. $type.' alert-dismissible fade show',['role' => 'alert']).
                $content.
                self::start_tag('button',['type'=>"button", 'class'=>"close", 'data-dismiss'=>"alert", 'aria-label'=>"Close"]).
                    self::span('x','',['aria-hidden'=>'true']).
                self::end_tag('button').
            self::end_div();

        return $output;
    }

    public static function createHeading($icon, $content):string {

        $output = self::start_div('p-3  mt-3 align-items-center my-3 shadow-sm bg-dark rounded text-white');
        $output .= self::tag('h3',$icon . $content);
        $output .= self::end_div();

        return $output;
    }

    public static function createMQCheckBoxes($elements,$attr):string {

        $output = self::start_div('custom-control custom-checkbox');
        foreach ($elements as $element){
            $id = 'mq-checkbox-'. $element['id'].'-'.microtime();
            $checkboxAttr = [
                'type'=>'checkbox',
                'class'=>'custom-control-input',
                'name'=> $attr['name'].'['.$element['id'].']',
                'value'=> $element['id'],
                'id' => $id
            ];
            if(key_exists('checked',$element))
                $checkboxAttr['checked'] = '';

            $output .=
                self::start_div('col-6 pl-4').
                self::tag('input','',$checkboxAttr
                ).
                self::tag('label',$element['name'],[
                    'class'=>"custom-control-label",
                    'for'=>$id
                ]).
                self::end_div();
        }
        $output .= self::end_div();

        return $output;
    }
}