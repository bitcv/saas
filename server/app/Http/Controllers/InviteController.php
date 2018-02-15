<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use Illuminate\Http\Request;
use App\Utils\Service;
use App\Models\Module;
use App\Models\Project;
use App\Utils\WxJSSDK;
use App\Utils\SMS;

class InviteController extends \App\Http\Controllers\Controller {

    public function getInvite(Request $request) {
        //$jssdk = new WxJSSDK('wx47ea3553f628923e', '707647d30c29289569d0c3ee0addaa8a');
        //$signPackage = $jssdk->GetSignPackage();
        //$sum = (new Invite())->getTotalToken();
        //$leftbcv = round((1000000-$sum['totalbcv'])/10000);
        //$leftdoge = round((1000000-$sum['totaldoge'])/10000);
        if (!Module::check('invite')) {
            die('err url');
        }

        $invite = new Invite();
        $uid        = Invite::decode(\Cookie::get('uid'));
        if ($uid) {
            $user = $invite->getByUid($uid);
        } else {
            $user = [];
        }

        $code       = $request->input('code', '');
        $proj = Project::where('id', app()->proj['proj_id'])->first()->toArray();
        return view('invite.add', compact('code', 'proj', 'user', 'leftbcv', 'leftdoge'));
    }

    public function vcode($mobile) {
        $ret = Service::getVcode('reg', $mobile);
        if ($ret['err'] > 0) {
            return ['retcode'=>1, 'msg'=>$ret['msg']];
        }
        Service::sms($mobile, '【BitCV】Your validation code is '.$ret['data'].', please input in 5 minutes');
        return ['retcode'=>200];
    }

    public function vcode2($mobile) {
        $ret = Service::getVcode('reg', $mobile);
        if ($ret['err'] > 0) {
            return ['retcode'=>1, 'msg'=>$ret['msg']];
        }
        SMS::sendVcode($mobile, $ret['data']);
        return ['retcode'=>200];
    }

    public function verifyCode(Request $request) {
        $vcode = $request->input('vcode');
        $mobile = $request->input('mobile');
        $code       = $request->input('code', '');
        $ret = Service::checkVCode('reg', $mobile, $vcode);
        if ($ret['err'] > 0) {
            return ['retcode' => 1, 'msg' => $ret['msg']];
        }

        $invite = new Invite();
        $fromid = $code ? Invite::decode($code) : 0;
        $ret    = $invite->getUidByMobile($mobile, $fromid);
        \Cookie::queue('uid', Invite::encode($ret['data']['uid']), 43200);//单位是分钟

        return $ret;
    }

    public function add(Request $request) {
        return '';
        $uid        = Invite::decode(\Cookie::get('uid'));
        $address    = $request->input('address');
        $code       = $request->input('code', '');
        if (!preg_match('/[0-9a-zA-Z]{30,50}/', $address)) {
            $data = array(
                'retcode'   => 40001,
                'msg'       => '请输入正确格式的以太坊钱包地址！',
            );

            return $data;
        }

        //验证address
        try {
            $result = (new Invite())->invites($code, $address, $uid);
        } catch(\Exception $e) {
            $data = array(
                'retcode'   => $e->getCode(),
                'msg'       => $e->getMessage()
            );

            return $data;
        }


        return $result;
    }
}