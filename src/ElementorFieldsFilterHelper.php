<?php namespace KPS3\Smartling\Elementor;

use Smartling\Bootstrap;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Submissions\SubmissionEntity;

class ElementorFieldsFilterHelper extends FieldsFilterHelper {

    use LoggerSafeTrait;

    private static function getFieldProcessingParams()
    {
        return Bootstrap::getContainer()->getParameter('field.processor');
    }

    /**
     * Override the default fields filter helper because we need to flatten the data array after processing the field.
     */
    public function processStringsBeforeEncoding(
        SubmissionEntity $submission,
        array $data,
        #[ExpectedValues([self::FILTER_STRATEGY_UPLOAD, self::FILTER_STRATEGY_DOWNLOAD])]
        string $strategy = self::FILTER_STRATEGY_UPLOAD
    ): array {
        ContentSerializationHelper::prepareFieldProcessorValues($this->getSettingsManager(), $submission);
        $data = $this->prepareSourceData($data);
        $data = $this->flattenArray($data);

        $settings = self::getFieldProcessingParams();
        $data = $this->removeFields($data, $settings['ignore'], false);
        $data = $this->passFieldProcessorsBeforeSendFilters($submission, $data);

        $data = $this->flattenArray($data); // Added in this override to handle pulling out multiple fields from a elementor array.

        $data = $this->passConnectionProfileFilters($data, $strategy, false);

        $this->getLogger()->info(print_r($data, true));

        return $data;
    }

    /**
     * Is used while downloading
     *
     * @param SubmissionEntity $submission
     * @param array $originalValues
     * @param array $translatedValues
     * @param bool $applyFilters
     *
     * @return array
     */
    public function applyTranslatedValues(SubmissionEntity $submission, array $originalValues, array $translatedValues, $applyFilters = true): array
    {
        $this->getLogger()->debug('Smartling-Elementor is applying translated values');
        ElementorProcessor::SetSubmission($submission);
        $settingsManager = $this->getSettingsManager();
        $profile = $settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
        if ($profile->getCleanMetadataOnDownload() !== 1) {
            $profile->setCleanMetadataOnDownload(1);
            $this->getLogger()->notice('Setting cleanMetadataOnDownload to true for Elementor metadata');
            $settingsManager->storeEntity($profile);
        }


        $originalValues = $this->prepareSourceData($originalValues);
        $originalValues = $this->flattenArray($originalValues);

        $translatedValues = $this->prepareSourceData($translatedValues);
        $translatedValues = $this->flattenArray($translatedValues);

        $result = [];

        if (true === $applyFilters) {
            ContentSerializationHelper::prepareFieldProcessorValues($this->getSettingsManager(), $submission);

            $filteredOriginalValues = $this->filterArray($originalValues, $submission, self::FILTER_STRATEGY_DOWNLOAD);
            $result = array_merge($result, $filteredOriginalValues);
        } else {
            $result = $originalValues;
        }

        $result = array_merge($result, $translatedValues);
        $result = apply_filters(ElementorProcessor::FILTER_ELEMENTOR_DATA_FIELD_PROCESS, $result);
        foreach ($result as $key => $value) {
            if (preg_match( '~^meta/_elementor_data/~', $key) === 1) {
                $this->getLogger()->debug( "Removed $key='$value'");
                unset($result[$key]);
            }
        }

        return $this->structurizeArray($result);
    }

    private function filterArray(array $array, SubmissionEntity $submission, string $strategy): array
    {
        $settings = self::getFieldProcessingParams();
        $settings['ignore'][] = 'ID';
        $array = $this->removeFields($array, $settings['ignore'], false);

        $array = $this->passFieldProcessorsFilters($submission, $array);

        return $this->passConnectionProfileFilters($array, $strategy, false);
    }
}
