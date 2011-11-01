<?php

class CmsController extends BaseController
{
	private $_templateMatch = null;

	public function init()
	{
		parent::init();
		$this->_templateMatch = Blocks::app()->getUrlManager()->getTemplateMatch();
	}
/*
 * if(($action=$this->createAction($actionID))!==null)
		{
			if(($parent=$this->getModule())===null)
				$parent=Yii::app();
			if($parent->beforeControllerAction($this,$action))
			{
				$this->runActionWithFilters($action,$this->filters());
				$parent->afterControllerAction($this,$action);
			}
		}
		else
			$this->missingAction($actionID);
 *
 */
	public function run($actionId)
	{
		// this will run through the filterchain on the cms controller.
		parent::run($actionId);

		if ($this->_templateMatch !== null || Blocks::app()->request->getParam('c', null) !== null)
		{
			if ($this->_templateMatch !== null)
			{
				$tempController = $this->_templateMatch->getRelativePath();
				$tempAction = $this->_templateMatch->getFileName();
			}
			else
			{
				$tempController = Blocks::app()->request->getParam('c');
				$pathSegs = Blocks::app()->request->getPathSegments();
				$tempAction = $pathSegs[0];
			}

			// we found a matching controller for this request.
			if (($ca = Blocks::app()->createController($tempController)) !== null)
			{
				//list($requestController, $actionID) = $ca;
				$this->setRequestController($ca[0]);
				// save the current controller and swap out the new one.
				$oldController = Blocks::app()->getController();
				Blocks::app()->setController($this->getRequestController());

				// there is an explicit request to a controller and action
				if (Blocks::app()->request->getParam('c', null) !== null
				    || (Blocks::app()->controller->getModule() !== null && Blocks::app()->controller->getModule()->id == 'install')
				    || Blocks::app()->controller->id == 'update')
				{
					Blocks::app()->controller->init();
					// now we run through the filterchain on the swapped out controller.
					//parent::run($tempAction);
					Blocks::app()->controller->run($tempAction);
				}
				else
				{
					// controller request, but no action specified, so just render template.
					$this->showTemplate($tempAction);
				}

				Blocks::app()->setController($oldController);
			}
			// no matching controller, so just render the template.
			else
			{
				$this->showTemplate($tempAction);
			}
		}
		else
		{
			throw new BlocksHttpException('404', 'Page not found.');
		}
	}

	public function actionIndex()
	{
	}


	//$realTemplate = Blocks::app()->file->set($realPath, false);
	//if ($realTemplate->getExists())
	//{

	//	$this->_templateMatch['templatePath'] = $realTemplate->getRealPath();

		// see if it's already been translated, if so check last modified dates, if same, return
		//$translatedFile = Blocks::app()->file->set($templateCachePath.$path.$testFileName);

		//if (!$translatedFile->getExists() || strtotime($realLastModified) > strtotime($translatedFile->getLastModified()))
		//{

		//}
	//}


	/*protected function afterAction($action)
	{
		$test = $this->_templateMatch['path'] == '' ? $this->_templateMatch['file'] : $this->_templateMatch['path'].'/'.$this->_templateMatch['file'];
		$this->render($test);
	}*/

	private function cacheTemplate($sourceTemplatePath)
	{
		if (StringHelper::IsNullOrEmpty($sourceTemplatePath))
			throw new BlocksException('Source template path is required.');

		$sourceTemplate = Blocks::app()->file->set($sourceTemplatePath, false);

		if (!$sourceTemplate->getExists())
			throw new BlocksException('Cannot find the source template path: '.$sourceTemplatePath);

		if (Blocks::app()->request->getCMSRequestType()  == RequestType::ControlPanel)
				$cachedTemplatePath = Blocks::app()->templateCPCache->getCachedTemplatePath($sourceTemplate->getRealPath());
			else
				$cachedTemplatePath = Blocks::app()->templateSiteCache->getCachedTemplatePath($sourceTemplate->getRealPath());

			$cachedTemplateFile = Blocks::app()->file->set($cachedTemplatePath, false);
			$realLastModified = $sourceTemplate->getTimeModified();

			// if the cached template file does not exist OR it exists but the source has a newer modified date.
			if (!$cachedTemplateFile->getExists() || $realLastModified < $cachedTemplateFile->getTimeModified())
			{
				// translate the source template.
				$translator = new TemplateTranslator();
				$translator->translate($sourceTemplate->getRealPath());

				$cachedTemplateFile->refresh();
				// it should exist in cache now.
				if ($cachedTemplateFile->getExists())
					return $cachedTemplateFile->getContents();
			}
			else
			{
				return $cachedTemplateFile->getContents();
			}
	}
}
