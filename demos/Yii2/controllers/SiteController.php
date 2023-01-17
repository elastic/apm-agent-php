<?php

namespace app\controllers;

use app\models\Article;
use app\models\EntryForm;
use Yii;
use yii\data\Pagination;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;

class SiteController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    public function actionAbout()
    {
        return $this->render('about');
    }

    public function actionBlog()
    {
        $query = Article::find();

        $pagination = new Pagination([
            'defaultPageSize' => 5,
            'totalCount' => $query->count(),
        ]);

        $articles = $query->orderBy('id')
            ->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        return $this->render('blog', [
            'articles' => $articles,
            'pagination' => $pagination,
        ]);
    }

    public function actionPost()
    {
        $model = new EntryForm();
        $article = new Article();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $form = Yii::$app->request->bodyParams['EntryForm'];

            $article->setAttribute('title', $form['title']);
            $article->setAttribute('content', $form['content']);

            $article->save();

            return $this->redirect(['blog']);
        } else {
            return $this->render('add_post', ['model' => $model]);
        }
    }

    public function actionUpdate($id)
    {
        $model = Article::findOne($id);

        if ($model->load(Yii::$app->request->post())) {
            $form = Yii::$app->request->bodyParams['Article'];

            $model->setAttribute('title', $form['title']);
            $model->setAttribute('content', $form['content']);

            $model->save();

            return $this->redirect(['blog']);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    public function actionDelete($id)
    {
        Article::findOne($id)->delete();

        return $this->redirect(['blog']);
    }

    public function migrate()
    {
        $oldApp = \Yii::$app;
        $config = require \Yii::getAlias('@app'). '/config/console.php';
        new \yii\console\Application($config);
        $result = \Yii::$app->runAction('migrate', ['migrationPath' => '@app/migrations/', 'interactive' => false]);

        \Yii::$app = $oldApp;

        return true;
    }
}
