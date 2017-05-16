<?php

Route::group(['prefix' => 'v1', 'namespace' => 'Modules\Vividcase\Http\Controllers'], function()
{
        /**********不需要验证token接口START**********/
        //登录接口
        Route::get('/Users/login/{username}/{userpwd}', 'UsersController@login');
        //检测手机号是否存在接口
        Route::get('/Users/isphonereg/{userphone}/{userid?}', 'UsersController@isphonereg');
        //注册接口
        Route::match(['GET', 'POST'], '/Users/register/', 'UsersController@phoneregister');
        //测试
        Route::get('/Users/testemail', 'UsersController@testemail');
        /**********不需要验证token接口END**********/

        /*******需要验证token；使用中间件start*******/
        //游客可以访问接口路由集合
        Route::group(['middleware' => 'vividcaseapi:1'], function() {
            Route::get('/Users/getgametop/{userid}/{usertoken}','UsersController@getgametop');
            //医药百科
            Route::get('/Drugwikipedia/onetypelst/{userid}/{usertoken}','DrugwikipediaController@onetypelst');
            Route::get('/Drugwikipedia/lowertypelst/{userid}/{usertoken}/{typetype}/{drugtypeid}','DrugwikipediaController@lowertypelst');
            Route::get('/Drugwikipedia/commonamelst/{userid}/{usertoken}/{ingredientid}/{typetype}/{peopletypeid?}','DrugwikipediaController@commonamelst');
            Route::get('/Drugwikipedia/tradenamelst/{userid}/{usertoken}/{commonid}/{typetype}/{cpage}','DrugwikipediaController@tradenamelst');
            Route::get('/Drugwikipedia/drugmsginfo/{userid}/{usertoken}/{drugid}/{typetype}/{msgtype}','DrugwikipediaController@drugmsginfo');
            Route::get('/Drugwikipedia/searchdrugmsg/{userid}/{usertoken}/{keywords}/{searchtype}/{cpage}','DrugwikipediaController@searchdrugmsg');
            Route::get('/Drugwikipedia/effectdatav2/{userid}/{usertoken}/{drugid}/{typetype}','DrugwikipediaController@effectdatav2');
            Route::get('/Drugwikipedia/hotsearch/{userid}/{usertoken}/{searchtype}','DrugwikipediaController@hotsearch');
            Route::get('/Drugwikipedia/drugmessage/{userid}/{usertoken}/{datatype}/{cpage}','DrugwikipediaController@drugmessage');
            Route::get('/Drugwikipedia/drugguidelst/{userid}/{usertoken}/{drugid}/{cpage}','DrugwikipediaController@drugguidelst');

        });
        //游客禁止访问接口路由集合
        Route::group(['middleware' => 'vividcaseapi:0'], function() {
            Route::post('/Users/savegamescord','UsersController@savegamescord');

            //基层医生
            Route::get('/Basedoctor/depdislst/{userid}/{usertoken}','BasedoctorController@depdislst');
            Route::get('/Basedoctor/dissearch/{userid}/{usertoken}/{disname}','BasedoctorController@dissearch');
            Route::get('/Basedoctor/disguidelst/{userid}/{usertoken}/{diseaseid}/{cpage}','BasedoctorController@disguidelst');
            Route::get('/Basedoctor/disdruglst/{userid}/{usertoken}/{diseasename}/{cpage}','BasedoctorController@disdruglst');
            Route::get('/Basedoctor/discaselst/{userid}/{usertoken}/{diseasename}/{cpage}','BasedoctorController@discaselst');
            Route::get('/Basedoctor/disclinicpathlst/{userid}/{usertoken}/{diseasename}/{cpage}','BasedoctorController@disclinicpathlst');
            Route::get('/Basedoctor/dischecklst/{userid}/{usertoken}/{diseasename}/{cpage}','BasedoctorController@dischecklst');
            Route::get('/Basedoctor/dismsg/{userid}/{usertoken}/{diseaseid}','BasedoctorController@dismsg');
            Route::get('/Basedoctor/guideclinicpathset/{userid}/{usertoken}/{diseaseid}','BasedoctorController@guideclinicpathset');
            Route::get('/Basedoctor/drugcheckset/{userid}/{usertoken}/{diseasename}','BasedoctorController@drugcheckset');
            Route::get('/Basedoctor/literaturemore/{userid}/{usertoken}/{diseasename}/{cpage}','BasedoctorController@literaturemore');
            Route::get('/Basedoctor/disvideo/{userid}/{usertoken}/{diseaseid}/{cpage}','BasedoctorController@disvideo');
            Route::get('/Basedoctor/disinformation/{userid}/{usertoken}/{diseaseid}/{cpage}','BasedoctorController@disinformation');
            Route::get('/Basedoctor/information/{userid}/{usertoken}','BasedoctorController@information');
            Route::get('/Basedoctor/homevideo/{userid}/{usertoken}','BasedoctorController@homevideo');
            Route::get('/Basedoctor/disstudy/{userid}/{usertoken}','BasedoctorController@disstudy');

            //3D人体图库
            Route::get('/Bodyatlas/bodylst/{userid}/{usertoken}','BodyatlasController@bodylst');
            Route::get('/Bodyatlas/bodytypelst/{userid}/{usertoken}/{typeid}','BodyatlasController@bodytypelst');
            Route::get('/Bodyatlas/localtypelst/{userid}/{usertoken}','BodyatlasController@localtypelst');
            Route::get('/Bodyatlas/ecgdatalst/{userid}/{usertoken}','BodyatlasController@ecgdatalst');
            Route::get('/Bodyatlas/bodyatlaslst/{userid}/{usertoken}/{typeid}','BodyatlasController@bodyatlaslst');
            Route::get('/Bodyatlas/bodytaglst/{userid}/{usertoken}/{bodyatlasid}','BodyatlasController@bodytaglst');
            Route::get('/Bodyatlas/searchtags/{userid}/{usertoken}/{keywords}','BodyatlasController@searchtags');
            Route::get('/Bodyatlas/ecgtypelst/{userid}/{usertoken}','BodyatlasController@ecgtypelst');
            Route::get('/Bodyatlas/ecgtypecaselst/{userid}/{usertoken}/{ecgtypeid}/{cpage}','BodyatlasController@ecgtypecaselst');

            //IM
            Route::get('/Im/getalluserdetail/{userid}/{usertoken}/{useridjson}','ImController@getalluserdetail');
            Route::get('/Im/getuserfriends/{userid}/{usertoken}','ImController@getuserfriends');
        });
        /*******需要验证token；使用中间件end*******/

});